/**
 * Visual Search — WinPixel Integration for Source Version (retail_v5)
 *
 * Flow:
 * 1. Upload screen: drag/drop, browse, or webcam capture
 * 2. On file/capture → auto search with step-by-step progress
 * 3. Results: top 1 match + "Show Similar" for the rest
 * 4. Click any image → modal with full details
 * 5. "New Search" → reset
 */
"use strict";

$(document).ready(function () {

  // ═══════════════════════════════════════════
  // Config
  // ═══════════════════════════════════════════
  var TOP_MATCH_COUNT = 1;

  // Detect base URL — works on any deployment (local /AMS/, staging /, production /)
  var _base = (function () {
    // 1. Try <base> tag (set by CI3 template)
    if ($("base").length) return $("base").attr("href");
    // 2. Derive from current URL: strip everything after last "index.php" or controller path
    var path = window.location.pathname;
    var idx = path.indexOf("index.php");
    if (idx > -1) return window.location.origin + path.substring(0, idx);
    // 3. Fallback: strip controller segment
    var parts = path.replace(/\/+$/, "").split("/");
    parts.pop(); // remove controller name
    return window.location.origin + parts.join("/") + "/";
  })();

  var searchUrl       = _base + "index.php/admin_visual_search/search";
  var tagBaseUrl       = _base + "index.php/admin_ret_tagging/tagging/edit/";

  var queryImgSrc   = null;
  var allResults    = [];
  var cameraStream  = null;
  var currentSessionId = null;
  var votes = {};  // { point_id: 'up'|'down' }
  var searchStartTime = 0;

  // DOM refs for new elements
  var $statsBanner    = $("#vsStatsBanner");
  var $historyPanel   = $("#vsHistoryPanel");
  var $historyList    = $("#vsHistoryList");

  var feedbackUrl      = _base + "index.php/admin_visual_search/feedback";
  var refineUrl        = _base + "index.php/admin_visual_search/refine";
  var tagDetailsUrl    = _base + "index.php/admin_visual_search/tag_details";
  var proxyAnalyticsUrl = _base + "index.php/admin_visual_search/proxy_analytics";
  // boardsUrl removed — Suggestive Boards is now a separate product

  // ═══════════════════════════════════════════
  // DOM refs
  // ═══════════════════════════════════════════
  var $uploadSection  = $("#vsUploadSection");
  var $resultsSection = $("#vsResultsSection");
  var $noResults      = $("#vsNoResults");
  var $newSearchBtn   = $("#vsNewSearchBtn");
  var $searchOverlay  = $("#vsSearchOverlay");
  var $fileInput      = $("#vs_file_input");
  var $dropzone       = $("#vsDropzone");
  var $topContainer   = $("#vsTopMatchesContainer");

  // Camera refs
  var $uploadMode     = $("#vsUploadMode");
  var $cameraMode     = $("#vsCameraMode");
  var $video          = $("#vsCameraVideo")[0];
  var $canvas         = $("#vsCameraCanvas")[0];


  // ═══════════════════════════════════════════
  // Progress — Matching Animation
  // ═══════════════════════════════════════════
  function resetProgress() {
    $("#vsMatchAnimBar").css("width", "0%");
    $("#vsProgressText").text("Searching across inventory...");
    // Show uploaded image in animation
    if (queryImgSrc) {
      $("#vsMatchAnimSrc").attr("src", queryImgSrc);
    }
  }

  function setStep(stepNum, text) {
    // Map old 4-step progress to progress bar percentage
    var pctMap = { 1: 15, 2: 40, 3: 70, 4: 100 };
    var pct = pctMap[stepNum] || 0;
    $("#vsMatchAnimBar").css("width", pct + "%");
    $("#vsProgressText").text(text);
  }

  // ═══════════════════════════════════════════
  // Upload — Click or Drag & Drop
  // ═══════════════════════════════════════════
  $dropzone.on("click", function (e) {
    // Don't trigger if click came from the Browse Files button (it has its own onclick)
    if ($(e.target).closest(".vs-cta-upload").length) return;
    $fileInput.trigger("click");
  });

  $fileInput.on("change", function () {
    if (this.files && this.files[0]) handleFile(this.files[0]);
  });

  $dropzone.on("dragover", function (e) {
    e.preventDefault(); e.stopPropagation();
    $(this).addClass("vs-dragover");
  });
  $dropzone.on("dragleave drop", function (e) {
    e.preventDefault(); e.stopPropagation();
    $(this).removeClass("vs-dragover");
  });
  $dropzone.on("drop", function (e) {
    var files = e.originalEvent.dataTransfer.files;
    if (files && files[0]) handleFile(files[0]);
  });

  function handleFile(file) {
    var validTypes = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp"];
    if (validTypes.indexOf(file.type) === -1) {
      showAlert("danger", "Invalid file type. Please upload JPG, PNG, WebP, or BMP.");
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showAlert("danger", "File too large. Maximum 10MB allowed.");
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      queryImgSrc = e.target.result;
      doSearch(file);
    };
    reader.readAsDataURL(file);
  }

  // ═══════════════════════════════════════════
  // Webcam — Open / Capture / Close
  // ═══════════════════════════════════════════
  $("#vsCameraBtn").on("click", function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showAlert("danger", "Webcam not supported in this browser. Try Chrome or Edge.");
      return;
    }

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: "environment", width: { ideal: 1280 }, height: { ideal: 720 } }
    })
    .then(function (stream) {
      cameraStream = stream;
      $video.srcObject = stream;
      $uploadMode.hide();
      $cameraMode.fadeIn(200);
    })
    .catch(function (err) {
      showAlert("danger", "Could not access camera: " + err.message);
    });
  });

  $("#vsCaptureBtn").on("click", function () {
    if (!cameraStream) return;

    // Draw video frame to canvas
    $canvas.width  = $video.videoWidth;
    $canvas.height = $video.videoHeight;
    var ctx = $canvas.getContext("2d");
    ctx.drawImage($video, 0, 0);

    // Get image data
    queryImgSrc = $canvas.toDataURL("image/jpeg", 0.92);

    // Convert data URL to Blob for upload
    $canvas.toBlob(function (blob) {
      stopCamera();
      var file = new File([blob], "webcam_capture.jpg", { type: "image/jpeg" });
      doSearch(file);
    }, "image/jpeg", 0.92);
  });

  $("#vsCancelCameraBtn").on("click", function () {
    stopCamera();
  });

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(function (track) { track.stop(); });
      cameraStream = null;
    }
    $cameraMode.hide();
    $uploadMode.fadeIn(200);
  }

  // ═══════════════════════════════════════════
  // Search with Progress Steps
  // ═══════════════════════════════════════════
  function doSearch(file) {
    searchStartTime = Date.now();
    var formData = new FormData();
    formData.append("image", file);

    // Reset UI
    resetProgress();
    $searchOverlay.css("display", "flex");
    $("body").css("overflow", "hidden");
    $resultsSection.hide();
    $noResults.hide();
    $("#vsShowMoreBtn").hide().data("expanded", false);
    $("#vsOnSaleTag").hide();
    $topContainer.empty();

    // Step 1: Uploading
    setStep(1, "Uploading image to server...");

    $.ajax({
      url: searchUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      timeout: 90000,
      xhr: function () {
        var xhr = new window.XMLHttpRequest();
        xhr.upload.addEventListener("progress", function (e) {
          if (e.lengthComputable) {
            var pct = Math.round((e.loaded / e.total) * 100);
            setStep(1, "Uploading image... " + pct + "%");
            if (pct >= 100) {
              setStep(2, "Processing image on server...");
              // Simulate processing step transition
              setTimeout(function () {
                setStep(3, "Searching across " + "inventory...");
              }, 600);
            }
          }
        });
        return xhr;
      },
      success: function (response) {
        var searchDuration = ((Date.now() - searchStartTime) / 1000).toFixed(1);
        // Step 4: Done
        setStep(4, "Found matches in " + searchDuration + "s!");

        setTimeout(function () {
          $searchOverlay.css("display", "none");
          $("body").css("overflow", "");

          var data = typeof response === "string" ? JSON.parse(response) : response;
          console.log("[VS] Search response keys:", Object.keys(data), "session_id:", data.session_id);

          if (data.status === false) {
            showAlert("danger", data.msg || "Search failed");
            return;
          }

          allResults = data.results || [];
          currentSessionId = data.session_id || ("local_" + Date.now());
          votes = {};
          if (allResults.length === 0) {
            $uploadSection.hide();
            $noResults.show();
            $newSearchBtn.show();
            $statsBanner.hide();
            return;
          }

          // Update stats banner
          $("#vsStatsCount").text(allResults.length);
          $("#vsStatsTime").text(searchDuration);
          $statsBanner.show();

          // Save to search history
          addToHistory(queryImgSrc, allResults.length, searchDuration, allResults);

          $uploadSection.hide();
          $newSearchBtn.show();

          // Render top N matches
          var topCount = Math.min(TOP_MATCH_COUNT, allResults.length);
          for (var i = 0; i < topCount; i++) {
            renderTopMatch(allResults[i], i, i === 0);
          }

          // Proactively fetch multi-branch availability for top cards
          for (var j = 0; j < topCount; j++) {
            (function(cardIdx) {
              var r = allResults[cardIdx];
              if (!r || !r.tag_code) return;
              $.ajax({
                url: tagDetailsUrl,
                type: "POST",
                data: { tag_code: r.tag_code },
                dataType: "json",
                success: function(resp) {
                  if (resp.status && resp.data) {
                    updateInlineBranch(cardIdx, resp.data);
                  }
                }
              });
            })(j);
          }

          // Fetch insights + suggestions in a single merged call
          var topMatch = allResults[0];
          if (topMatch) {
            fetchCollectionInsights(topMatch, allResults);
          }

          // Remaining go to similar items strip (inside best match card)
          var remaining = allResults.slice(topCount);
          if (remaining.length > 0) {
            renderSimilarStrips(remaining, topCount);
          }

          // Reset On Sale tag for new search
          $("#vsOnSaleTag").hide();

          $resultsSection.fadeIn(300);

          // Bottom bar removed — no longer needed
        }, 500);  // brief pause to show "Done" step
      },
      error: function (xhr, status) {
        $searchOverlay.css("display", "none");
        $("body").css("overflow", "");
        showAlert("danger", status === "timeout"
          ? "Search timed out. Please try again."
          : "Network error. Check your connection.");
      }
    });
  }

  // ═══════════════════════════════════════════
  // Top Match Card — Dark Spotlight Layout
  // ═══════════════════════════════════════════
  function renderTopMatch(r, index, isBest) {
    var score = r.score.toFixed(1);
    var scoreNum = parseFloat(score);
    var circumference = 2 * Math.PI * 38;
    var offset = circumference - (scoreNum / 100) * circumference;
    var ringColor = scoreNum >= 80 ? "#10B981" : scoreNum >= 60 ? "#F59E0B" : "#EF4444";
    var label = isBest ? '<i class="fa fa-star"></i> Best Match' : '<i class="fa fa-trophy"></i> Match #' + (index + 1);
    var labelClass = isBest ? "label-success" : "label-info";

    // Check "On Sale" status from API (tag_status === 0 means On Sale)
    var tagStatus = parseInt(r.tag_status, 10);
    if (index === 0 && tagStatus === 0) {
      $("#vsOnSaleTag").show();
    }

    var html =
      '<div class="vs-match-card" data-match-index="' + index + '" style="animation: vs-fadeInUp 0.5s ease both; animation-delay:' + (index * 0.15) + 's;">' +
        '<div class="vs-match-card-header">' +
          '<span class="vs-match-label">' + label + '</span>' +
          '<span class="label ' + labelClass + ' vs-best-score-label">' + score + '% Match</span>' +
        '</div>' +
        '<div class="vs-match-card-body">' +
          // Side-by-side layout: images left, details right
          '<div class="vs-match-layout">' +
            // LEFT: Image comparison
            '<div class="vs-match-layout-left">' +
              '<div class="vs-comparison">' +
                '<div class="vs-compare-frame">' +
                  '<img class="vs-clickable-img" src="' + queryImgSrc + '" alt="Your Image">' +
                  '<span class="vs-click-hint"><i class="fa fa-search-plus"></i> Click for details</span>' +
                  '<div class="vs-frame-label">YOUR IMAGE</div>' +
                '</div>' +
                '<div class="vs-score-ring-wrap">' +
                  '<div class="vs-score-ring">' +
                    '<svg viewBox="0 0 80 80">' +
                      '<circle class="vs-score-ring-bg" cx="40" cy="40" r="38"></circle>' +
                      '<circle class="vs-score-ring-fill" cx="40" cy="40" r="38" ' +
                        'style="stroke:' + ringColor + '; stroke-dasharray:' + circumference.toFixed(1) + '; stroke-dashoffset:' + offset.toFixed(1) + ';"></circle>' +
                    '</svg>' +
                    '<div class="vs-score-ring-text">' + score + '%</div>' +
                  '</div>' +
                  '<div class="vs-score-ring-label">SCORE</div>' +
                '</div>' +
                '<div class="vs-compare-frame vs-tap-target">' +
                  '<img class="vs-compare-img vs-clickable-img" data-index="' + index + '" ' +
                    'src="' + esc(r.image_url) + '" alt="Match" ' +
                    'onerror="this.src=\'' + base_url + 'assets/dist/img/no_image_available.png\'">' +
                  '<span class="vs-click-hint"><i class="fa fa-search-plus"></i> Click for details</span>' +
                  '<div class="vs-tap-gesture">' +
                    '<div class="vs-hand-wrapper">' +
                      '<span class="vs-tap-ripple"></span>' +
                      '<span class="vs-tap-ripple vs-tap-ripple-2"></span>' +
                      '<img class="vs-hand-icon" src="' + base_url + 'assets/img/hand_click.png" alt="click">' +
                    '</div>' +
                  '</div>' +
                  '<div class="vs-frame-label">MATCHED PRODUCT</div>' +
                '</div>' +
              '</div>' +
              // Vote buttons under images
              '<div class="vs-vote-row-dark">' +
                '<button class="btn btn-lg btn-success vs-vote-btn" data-point="' + r.tag_id + '" data-tag="' + esc(r.tag_code) + '" data-vote="up">' +
                  '<i class="fa fa-thumbs-up"></i> Good Match' +
                '</button>' +
                '<button class="btn btn-lg btn-danger vs-vote-btn" data-point="' + r.tag_id + '" data-tag="' + esc(r.tag_code) + '" data-vote="down">' +
                  '<i class="fa fa-thumbs-down"></i> Bad Match' +
                '</button>' +
              '</div>' +
            '</div>' +
            // RIGHT: Product details
            '<div class="vs-match-layout-right">' +
              '<div class="vs-details-dark">' +
                '<div class="vs-detail-grid">' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Tag Code</span><span class="vs-dg-value">' + esc(r.tag_code || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Product</span><span class="vs-dg-value">' + esc(r.product_name || "N/A") + (r.design_name ? ' - ' + esc(r.design_name) : '') + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Sub-Design</span><span class="vs-dg-value">' + esc(r.sub_design_name || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Branch</span><span class="vs-dg-value" id="vsBranchInline' + index + '">' + esc(r.branch_name || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">G.WT / NWT</span><span class="vs-dg-value">' + fmtWt(r.gross_wt) + 'g / ' + fmtWt(r.net_wt) + 'g</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Status</span><span class="vs-dg-value"><span class="label ' + (parseInt(r.tag_status,10)===0?'label-success':parseInt(r.tag_status,10)===1?'label-danger':'label-warning') + '" style="font-size:11px;">' + statusText(r.tag_status) + '</span></span></div>' +
                '</div>' +
              '</div>' +
              '<div class="vs-details-dark" style="margin-top:2px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.08);">' +
                '<div class="vs-detail-grid">' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Category</span><span class="vs-dg-value">' + esc(r.category_name || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Stock Code</span><span class="vs-dg-value">' + esc(r.stock_code || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Purity</span><span class="vs-dg-value">' + esc(r.purity || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Karigar</span><span class="vs-dg-value">' + esc(r.karigar_code || "N/A") + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">' + (parseInt(r.tag_status_raw,10)===1 && r.sold_date ? 'Sold Date' : 'Stock Age') + '</span><span class="vs-dg-value">' + (parseInt(r.tag_status_raw,10)===1 && r.sold_date ? esc(r.sold_date) : (r.stock_age_days ? r.stock_age_days + ' days' : 'N/A')) + '</span></div>' +
                  '<div class="vs-dg-item"><span class="vs-dg-label">Status</span><span class="vs-dg-value"><span class="label ' + (parseInt(r.tag_status_raw,10)===0?'label-success':parseInt(r.tag_status_raw,10)===1?'label-danger':'label-warning') + '" style="font-size:11px;">' + esc(r.tag_status_text || "N/A") + '</span></span></div>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>';

        // Similar items toggle only for best match (avoids duplicate sections)
        if (isBest) {
          html +=
            '<button class="btn vs-similar-toggle-btn" data-card="' + index + '">' +
              'Near By Matching Items <i class="fa fa-chevron-down"></i>' +
            '</button>' +
            '<div class="vs-similar-strip" id="vsSimilarStrip' + index + '">' +
              '<div class="vs-similar-scroll" id="vsSimilarScroll' + index + '"></div>' +
            '</div>';
        }

        html +=
        '</div>' +
      '</div>';

    $topContainer.append(html);
  }

  // ═══════════════════════════════════════════
  // Similar Items — Card Grid (inside each match card)
  // ═══════════════════════════════════════════
  function renderSimilarStrips(results, topCount) {
    var $scroll = $("#vsSimilarScroll0");
    var $strip = $("#vsSimilarStrip0");
    if (!$scroll.length) return;

    $.each(results, function (i, r) {
      var globalIdx = topCount + i;
      var scoreNum = parseFloat(r.score) || 0;
      var ringColor = scoreNum >= 80 ? "#10B981" : scoreNum >= 60 ? "#F59E0B" : "#EF4444";
      var card =
        '<div class="vs-sim-card" data-index="' + globalIdx + '">' +
          '<div class="vs-sim-card-img">' +
            '<img src="' + esc(r.image_url) + '" alt="Match" ' +
              'onerror="this.src=\'' + base_url + 'assets/dist/img/no_image_available.png\'">' +
            '<span class="vs-sim-card-score" style="background:' + ringColor + '">' + scoreNum.toFixed(0) + '%</span>' +
          '</div>' +
          '<div class="vs-sim-card-body">' +
            '<div class="vs-sim-card-tag">' + esc(r.tag_code || "N/A") + '</div>' +
            '<div class="vs-sim-card-product">' + esc(r.product_name || r.design_name || "N/A") + '</div>' +
            '<div class="vs-sim-card-meta">' +
              '<span><i class="fa fa-map-marker"></i> ' + esc(r.branch_name || "-") + '</span>' +
              '<span>' + fmtWt(r.gross_wt) + 'g</span>' +
              '<span class="label ' + (parseInt(r.tag_status,10)===0?'label-success':parseInt(r.tag_status,10)===1?'label-danger':'label-warning') + '" style="font-size:9px;padding:1px 5px;">' + statusText(r.tag_status) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
      $scroll.append(card);
    });

    // Add scroll arrow buttons (if > 3 items)
    if (results.length > 3) {
      $strip.prepend(
        '<button class="vs-similar-arrow vs-similar-arrow-left" data-dir="left" data-card="0">' +
          '<i class="fa fa-chevron-left"></i>' +
        '</button>'
      );
      $strip.append(
        '<button class="vs-similar-arrow vs-similar-arrow-right" data-dir="right" data-card="0">' +
          '<i class="fa fa-chevron-right"></i>' +
        '</button>'
      );
    }

    // Update toggle button text with count and auto-expand (uncollapsed by default)
    var $toggleBtn = $(".vs-similar-toggle-btn[data-card='0']");
    $toggleBtn.addClass("active").html(
      'Hide Near By Matching Items (' + results.length + ') <i class="fa fa-chevron-up"></i>'
    );
    $strip.addClass("active").show();
  }

  // Scroll arrows for similar strip
  $(document).on("click", ".vs-similar-arrow", function () {
    var cardIdx = $(this).data("card");
    var dir = $(this).data("dir");
    var $scroll = $("#vsSimilarScroll" + cardIdx);
    var scrollAmount = dir === "left" ? -200 : 200;
    $scroll.animate({ scrollLeft: $scroll.scrollLeft() + scrollAmount }, 200);
  });

  // Toggle similar items strip
  $(document).on("click", ".vs-similar-toggle-btn", function () {
    var cardIdx = $(this).data("card");
    var $strip = $("#vsSimilarStrip" + cardIdx);
    var $btn = $(this);

    if ($strip.hasClass("active")) {
      $strip.slideUp(200, function () { $strip.removeClass("active"); });
      $btn.removeClass("active").html(
        $btn.html().replace("Hide Near By Matching Items", "Near By Matching Items").replace("fa-chevron-up", "fa-chevron-down")
      );
    } else {
      $strip.addClass("active").hide().slideDown(200);
      $btn.addClass("active").html(
        $btn.html().replace("Near By Matching Items", "Hide Near By Matching Items").replace("fa-chevron-down", "fa-chevron-up")
      );
    }
  });

  // Click similar card → open detail modal directly
  $(document).on("click", ".vs-sim-card", function () {
    var idx = parseInt($(this).attr("data-index"), 10);
    if (isNaN(idx) || !allResults[idx]) return;

    // Highlight active card
    $(".vs-sim-card").removeClass("active");
    $(this).addClass("active");

    // Open detail modal directly (similar items don't have .vs-clickable-img rendered)
    var r = allResults[idx];
    $("#vsModalTitle").html('<i class="fa fa-tag"></i> ' + esc(r.tag_code || "Product Details"));
    $("#vsModalImg").attr("src", r.image_url);
    $("#vsModalTagCode").text(r.tag_code || "N/A");

    var scoreVal = r.score.toFixed(1) + "% Match";
    var scoreClass = r.score >= 80 ? "label-success" : r.score >= 60 ? "label-warning" : "label-danger";
    $("#vsModalScore").text(scoreVal).removeClass("label-success label-warning label-danger").addClass(scoreClass);

    var loadingFields = ["#vsModalCategory", "#vsModalProduct",
                         "#vsModalDesign", "#vsModalSubDesign", "#vsModalGrossWt",
                         "#vsModalNetWt", "#vsModalPiece", "#vsModalPurity",
                         "#vsModalBranch", "#vsModalStatus", "#vsModalKarigar",
                         "#vsModalLessWt", "#vsModalStockAge", "#vsModalSize"];
    $.each(loadingFields, function(_, sel) { $(sel).html('<i class="fa fa-spinner fa-spin"></i>'); });
    $("#vsModalStoneGrid").remove();
    $("#vsDetailModal").modal("show");

    $.ajax({
      url: tagDetailsUrl,
      type: "POST",
      data: { tag_code: r.tag_code },
      dataType: "json",
      success: function (resp) {
        if (resp.status && resp.data) {
          fillModalFromERP(resp.data);
          updateInlineBranch(idx, resp.data);
        } else {
          fillModalFromQdrant(r);
        }
      },
      error: function () { fillModalFromQdrant(r); }
    });
  });

  // Show More / Show Less for cards beyond top 2
  $(document).on("click", "#vsShowMoreBtn", function () {
    var $btn = $(this);
    var showing = $btn.data("expanded");

    if (showing) {
      // Hide extra cards
      $(".vs-match-card[data-match-index]").each(function () {
        var idx = parseInt($(this).data("match-index"), 10);
        if (idx >= TOP_MATCH_COUNT) $(this).slideUp(200);
      });
      $btn.data("expanded", false);
      $btn.html('<i class="fa fa-chevron-down"></i> Show More Matches <span class="badge" style="background:rgba(255,255,255,0.2); margin-left:8px;">' + (allResults.length - TOP_MATCH_COUNT) + '</span>');
    } else {
      // Render and show remaining cards
      for (var i = TOP_MATCH_COUNT; i < allResults.length; i++) {
        if (!$(".vs-match-card[data-match-index='" + i + "']").length) {
          renderTopMatch(allResults[i], i, false);
        }
        $(".vs-match-card[data-match-index='" + i + "']").slideDown(200);
      }
      $btn.data("expanded", true);
      $btn.html('<i class="fa fa-chevron-up"></i> Show Less');
    }
  });

  // Spotlight New Search button
  $(document).on("click", "#vsSpotlightNewSearch", function () {
    $("#vsNewSearchBtn").trigger("click");
  });

  // ═══════════════════════════════════════════
  // Image Click → Detail Modal (Live ERP Fetch)
  // ═══════════════════════════════════════════
  $(document).on("click", ".vs-clickable-img[data-index]", function () {
    var idx = parseInt($(this).attr("data-index"), 10);
    if (isNaN(idx) || !allResults[idx]) return;

    // Hide tap gesture after first click
    $(this).closest(".vs-tap-target").addClass("vs-tapped");

    var r = allResults[idx];

    // Show modal immediately with Qdrant data (image + score)
    $("#vsModalTitle").html('<i class="fa fa-tag"></i> ' + esc(r.tag_code || "Product Details"));
    $("#vsModalImg").attr("src", r.image_url);
    $("#vsModalTagCode").text(r.tag_code || "N/A");

    var scoreVal = r.score.toFixed(1) + "% Match";
    var scoreClass = r.score >= 80 ? "label-success" : r.score >= 60 ? "label-warning" : "label-danger";
    $("#vsModalScore").text(scoreVal).removeClass("label-success label-warning label-danger").addClass(scoreClass);

    // Reset fields to loading state while fetching live data
    var loadingFields = ["#vsModalCategory", "#vsModalProduct",
                         "#vsModalDesign", "#vsModalSubDesign", "#vsModalGrossWt",
                         "#vsModalNetWt", "#vsModalPiece", "#vsModalPurity",
                         "#vsModalBranch", "#vsModalStatus", "#vsModalKarigar",
                         "#vsModalLessWt", "#vsModalStockAge", "#vsModalSize"];
    $.each(loadingFields, function(_, sel) { $(sel).html('<i class="fa fa-spinner fa-spin"></i>'); });
    $("#vsModalStoneGrid").remove(); // clear previous stone rows
    $("#vsModalViewTag").attr("href", tagBaseUrl + r.tag_id);
    $("#vsDetailModal").modal("show");

    // Fetch live details from ERP via CI3 controller
    $.ajax({
      url: tagDetailsUrl,
      type: "POST",
      data: { tag_code: r.tag_code },
      dataType: "json",
      success: function (resp) {
        if (resp.status && resp.data) {
          fillModalFromERP(resp.data);
          // Update inline card branch with multi-branch data
          updateInlineBranch(idx, resp.data);
        } else {
          fillModalFromQdrant(r);
        }
      },
      error: function () {
        fillModalFromQdrant(r);
      }
    });
  });

  // Helper: fill modal from ERP response
  function fillModalFromERP(d) {
    $("#vsModalCategory").text(d.category_name || "N/A");
    $("#vsModalProduct").text(d.product_name || "N/A");
    $("#vsModalDesign").text(d.design_name || "N/A");
    $("#vsModalSubDesign").text(d.sub_design_name || "N/A");
    $("#vsModalGrossWt").text(fmtWt(d.gross_wt) + " g");
    $("#vsModalNetWt").text(fmtWt(d.net_wt) + " g");
    $("#vsModalPiece").text(d.piece || "N/A");
    $("#vsModalPurity").text(d.purity || "N/A");
    // Multi-branch: show all branches where same combo is available
    if (d.available_branches && d.available_branches.length > 0) {
      var currentBranch = (d.branch_name || "").trim();
      var branchHtml = d.available_branches.map(function(b) {
        // Bold the current tag's branch
        return b === currentBranch
          ? "<strong>" + esc(b) + "</strong>"
          : esc(b);
      }).join(", ");
      $("#vsModalBranch").html(branchHtml);
    } else {
      $("#vsModalBranch").text(d.branch_name || "N/A");
    }
    $("#vsModalStatus").text(d.tag_status_text || "-");
    $("#vsModalKarigar").text(d.karigar_code || "N/A");
    $("#vsModalLessWt").text(d.less_wt ? parseFloat(d.less_wt).toFixed(3) + " g" : "0.000 g");
    $("#vsModalSize").text(d.size_name || "N/A");
    // Stock age vs Sold date: depends on tag status
    var isSold = (parseInt(d.tag_status_raw, 10) === 1);
    if (isSold && d.sold_date) {
      // Sold item — show sold date
      $("#vsModalStockAgeLabel").text("Sold Date");
      $("#vsModalStockAge").text(d.sold_date).css("color", "");
    } else if (d.tag_date && d.stock_age_days !== undefined) {
      // On sale / other — show stock age
      $("#vsModalStockAgeLabel").text("Stock Age");
      var ageDays = parseInt(d.stock_age_days, 10);
      var ageText = d.tag_date + " (" + ageDays + " days)";
      $("#vsModalStockAge").text(ageText);
      // Color-code: >180 days red, >90 amber, else normal
      if (ageDays > 180) { $("#vsModalStockAge").css("color", "#E53E3E"); }
      else if (ageDays > 90) { $("#vsModalStockAge").css("color", "#DD6B20"); }
      else { $("#vsModalStockAge").css("color", ""); }
    } else {
      $("#vsModalStockAgeLabel").text("Stock Age");
      $("#vsModalStockAge").text("N/A").css("color", "");
    }

    // Stone details (if any)
    if (d.stone_details && d.stone_details.length > 0) {
      var stoneHtml = '<tr id="vsModalStoneDetails"><th>Stone Details</th><td>';
      stoneHtml += '<table class="table table-condensed" style="margin:0;font-size:12px;">';
      stoneHtml += '<thead><tr><th>Stone</th><th>Pcs</th><th>Wt</th></tr></thead><tbody>';
      $.each(d.stone_details, function (_, s) {
        stoneHtml += '<tr><td>' + esc(s.stone_name) + '</td>';
        stoneHtml += '<td>' + s.stone_pcs + '</td>';
        stoneHtml += '<td>' + parseFloat(s.stone_wt).toFixed(3) + '</td></tr>';
      });
      stoneHtml += '</tbody></table></td></tr>';
      $(".vs-modal-table tr:last").after(stoneHtml);
    }
  }

  // Helper: fill modal from Qdrant payload (fallback)
  function fillModalFromQdrant(r) {
    $("#vsModalCategory").text(r.category_name || "N/A");
    $("#vsModalProduct").text(r.product_name || "N/A");
    $("#vsModalDesign").text(r.design_name || "N/A");
    $("#vsModalSubDesign").text(r.sub_design_name || "N/A");
    $("#vsModalGrossWt").text(fmtWt(r.gross_wt) + " g");
    $("#vsModalNetWt").text(fmtWt(r.net_wt) + " g");
    $("#vsModalPiece").text(r.piece || "N/A");
    $("#vsModalPurity").text(r.purity || "N/A");
    $("#vsModalBranch").text(r.branch_name || "N/A");
    $("#vsModalStatus").text(r.tag_status_text || statusText(r.tag_status));
    $("#vsModalKarigar").text("N/A");
    $("#vsModalLessWt").text("N/A");
    $("#vsModalStockAge").text("N/A").css("color", "");
    $("#vsModalSize").text("N/A");
  }


  // Helper: update inline card's branch field with multi-branch data from ERP
  function updateInlineBranch(index, d) {
    var $el = $("#vsBranchInline" + index);
    if (!$el.length) return;
    if (d.available_branches && d.available_branches.length > 0) {
      var currentBranch = (d.branch_name || "").trim();
      var html = d.available_branches.map(function(b) {
        return b === currentBranch
          ? "<strong>" + esc(b) + "</strong>"
          : esc(b);
      }).join(", ");
      $el.html(html);
    }
  }

  // ═══════════════════════════════════════════
  // Image Zoom Lightbox
  // ═══════════════════════════════════════════
  $(document).on("click", ".vs-modal-img-wrap", function () {
    var src = $("#vsModalImg").attr("src");
    if (src) {
      $("#vsLightboxImg").attr("src", src);
      $("#vsImageLightbox").fadeIn(200);
    }
  });
  $("#vsLightboxClose, .vs-lightbox-backdrop").on("click", function () {
    $("#vsImageLightbox").fadeOut(200);
  });
  $(document).on("keydown", function (e) {
    if (e.which === 27 && $("#vsImageLightbox").is(":visible")) {
      $("#vsImageLightbox").fadeOut(200);
    }
  });

  // ═══════════════════════════════════════════
  // New Search — Reset Everything
  // ═══════════════════════════════════════════
  $("#vsNewSearchBtn, #vsRetryBtn, #vsBottomNewSearch").on("click", function () {
    stopCamera();
    queryImgSrc = null;
    allResults  = [];
    currentSessionId = null;
    votes = {};
    $fileInput.val("");
    $topContainer.empty();
    $resultsSection.hide();
    $noResults.hide();
    $("#vsBottomBar").hide();
    $newSearchBtn.hide();
    $("#vsAgeFilter").hide();
    $statsBanner.hide();
    // Reset spotlight elements
    $("#vsOnSaleTag").hide();
    $("#vsShowMoreBtn").hide().data("expanded", false);
    $("#vsRefineBar").remove();
    // Reset insights sidebar
    $("#vsInsightCards").html(
      '<div id="vsInsightsLoader" class="vs-insights-loader">' +
        '<div class="vs-insight-skeleton"></div>' +
        '<div class="vs-insight-skeleton"></div>' +
        '<div class="vs-insight-skeleton"></div>' +
      '</div>'
    );
    $uploadMode.show();
    $cameraMode.hide();
    $uploadSection.fadeIn(300);
    loadHistory();
  });

  // ═══════════════════════════════════════════
  // Vote (Upvote / Downvote)
  // ═══════════════════════════════════════════
  $(document).on("click", ".vs-vote-btn", function (e) {
    e.stopPropagation();
    var $btn      = $(this);
    var pointId   = $btn.data("point");
    var tagCode   = $btn.data("tag");
    var voteType  = $btn.data("vote");  // 'up' or 'down'

    if (!currentSessionId) {
      showAlert("warning", "No active search session");
      return;
    }

    // Toggle: if already voted same way, un-vote (visual only)
    var prevVote = votes[pointId];
    if (prevVote === voteType) {
      delete votes[pointId];
      $btn.removeClass("active");
      updateRefineBar();
      return;
    }

    // Set vote state
    votes[pointId] = voteType;

    // Update all buttons for this point_id
    $(".vs-vote-btn[data-point='" + pointId + "']").removeClass("active");
    $(".vs-vote-btn[data-point='" + pointId + "'][data-vote='" + voteType + "']").addClass("active");

    // Send to server
    $.ajax({
      url: feedbackUrl,
      type: "POST",
      data: {
        session_id: currentSessionId,
        point_id:   pointId,
        tag_code:   tagCode,
        feedback:   voteType
      },
      success: function (res) {
        updateRefineBar();
        var msg = voteType === "up" ? "Marked as Good Match ✓" : "Marked as Bad Match ✗";
        showAlert("success", msg);
      },
      error: function () {
        // Still keep visual vote active — just note server issue
        updateRefineBar();
        showAlert("warning", "Vote saved locally (server unreachable)");
      }
    });
  });

  // ═══════════════════════════════════════════
  // Refine Bar — appears when votes exist
  // ═══════════════════════════════════════════
  function updateRefineBar() {
    var upCount   = 0;
    var downCount = 0;
    for (var k in votes) {
      if (votes[k] === "up") upCount++;
      else downCount++;
    }

    if (upCount + downCount === 0) {
      $("#vsRefineBar").remove();
      return;
    }

    if ($("#vsRefineBar").length === 0) {
      var bar =
        '<div id="vsRefineBar" class="vs-refine-bar">' +
          '<div class="vs-refine-info">' +
            '<span class="vs-refine-votes">' +
              '<i class="fa fa-thumbs-up text-success"></i> <span id="vsUpCount">0</span> ' +
              '<i class="fa fa-thumbs-down text-danger" style="margin-left:10px;"></i> <span id="vsDownCount">0</span>' +
            '</span>' +
          '</div>' +
          '<button class="btn btn-warning btn-sm" id="vsRefineBtn">' +
            '<i class="fa fa-magic"></i> Refine Results Based on Votes' +
          '</button>' +
        '</div>';
      $resultsSection.prepend(bar);
    }

    $("#vsUpCount").text(upCount);
    $("#vsDownCount").text(downCount);
  }

  // ═══════════════════════════════════════════
  // Refine — Re-rank via Qdrant recommend
  // ═══════════════════════════════════════════
  $(document).on("click", "#vsRefineBtn", function () {
    var $btn = $(this);
    if (!currentSessionId) return;

    $btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Refining...');

    $.ajax({
      url: refineUrl,
      type: "POST",
      data: { session_id: currentSessionId },
      success: function (response) {
        var data = typeof response === "string" ? JSON.parse(response) : response;
        console.log("[REFINE] Response:", data);

        if (data.status === false) {
          showAlert("warning", data.msg || data.detail || "Refinement failed");
          $btn.prop("disabled", false).html('<i class="fa fa-magic"></i> Refine Results Based on Votes');
          return;
        }

        if (!data.results || data.results.length === 0) {
          showAlert("info", "Refinement returned no results. Try different votes.");
          $btn.prop("disabled", false).html('<i class="fa fa-magic"></i> Refine Results Based on Votes');
          return;
        }

        // Re-render with refined results
        allResults = data.results;
        currentSessionId = data.session_id || currentSessionId;
        votes = {};
        $topContainer.empty();
        $("#vsOnSaleTag").hide();

        var topN = allResults.slice(0, TOP_MATCH_COUNT);
        var rest = allResults.slice(TOP_MATCH_COUNT);

        $.each(topN, function (i, r) {
          renderTopMatch(r, i, i === 0);
        });

        if (rest.length > 0) {
          renderSimilarStrips(rest, TOP_MATCH_COUNT);
          $("#vsRemainingCount").text(rest.length);
          $("#vsShowMoreBtn").show().data("expanded", false);
        } else {
          $("#vsShowMoreBtn").hide();
        }

        $("#vsRefineBar").remove();
        showAlert("success", "Results refined! Showing " + allResults.length + " updated matches.");
      },
      error: function () {
        showAlert("danger", "Refinement request failed");
        $btn.prop("disabled", false).html('<i class="fa fa-magic"></i> Refine Results Based on Votes');
      }
    });
  });

  // ═══════════════════════════════════════════
  // Helpers
  // ═══════════════════════════════════════════
  function scoreBg(s) {
    return s >= 80 ? "vs-score-high" : s >= 60 ? "vs-score-medium" : "vs-score-low";
  }
  function fmtWt(w) {
    return (w === null || w === undefined) ? "0.00" : parseFloat(w).toFixed(2);
  }
  function statusText(s) {
    var m = {
      0: "On Sale", 1: "Sold Out", 2: "Deleted", 3: "Other Issue",
      4: "In Transit", 5: "Deleted For Stock", 6: "Sales Return",
      7: "Tag Issue", 10: "Ready For Delivery"
    };
    return m[s] || "Unknown";
  }
  function esc(s) {
    if (!s) return "";
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }
  function showAlert(type, msg) {
    var icon = type === "danger" ? "ban" : type === "success" ? "check" : "info-circle";
    var html =
      '<div class="alert alert-' + type + ' alert-dismissible" style="margin:0 0 15px; border-radius:8px;">' +
        '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
        '<i class="fa fa-' + icon + '"></i> ' + msg +
      '</div>';
    $resultsSection.prepend(html);
    setTimeout(function () { $(".alert").fadeOut(300, function () { $(this).remove(); }); }, 5000);
  }

  // ═══════════════════════════════════════════
  // Search History (localStorage)
  // ═══════════════════════════════════════════
  // Derive key from base URL path so each client gets its own search history
  var HISTORY_KEY = "vs_search_history_" + _base.replace(/[^a-zA-Z0-9]/g, "_");
  var MAX_HISTORY = 5;

  function addToHistory(imgSrc, resultCount, duration, results) {
    if (!imgSrc) return;
    try {
      var history = JSON.parse(localStorage.getItem(HISTORY_KEY) || "[]");

      // Extract top 3 match tag details (compact, for display)
      var topTags = [];
      var tagLimit = Math.min(3, (results || []).length);
      for (var t = 0; t < tagLimit; t++) {
        var r = results[t];
        topTags.push({
          tag: r.tag_code || '',
          score: parseFloat(r.score) || 0,
          product: r.product_name || r.design_name || ''
        });
      }

      // Create a thumbnail (resize to 300px to keep enough detail for re-search)
      var canvas = document.createElement("canvas");
      var img = new Image();
      img.onload = function () {
        var scale = 300 / Math.max(img.width, img.height);
        canvas.width = Math.round(img.width * scale);
        canvas.height = Math.round(img.height * scale);
        canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
        var thumb = canvas.toDataURL("image/jpeg", 0.8);

        history.unshift({
          thumb: thumb,
          count: resultCount,
          time: duration,
          ts: Date.now(),
          tags: topTags,
          results: results || []
        });

        // Keep only last N
        if (history.length > MAX_HISTORY) history = history.slice(0, MAX_HISTORY);
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        renderHistory(history);
      };
      img.src = imgSrc;
    } catch (e) {
      // localStorage full or not available — silently ignore
    }
  }

  function loadHistory() {
    try {
      var history = JSON.parse(localStorage.getItem(HISTORY_KEY) || "[]");
      if (history.length > 0) {
        renderHistory(history);
        $historyPanel.show();
      } else {
        $historyPanel.hide();
      }
    } catch (e) {
      $historyPanel.hide();
    }
  }

  function renderHistory(history) {
    $historyList.empty();
    $.each(history, function (i, item) {
      var ago = timeAgo(item.ts);
      var resultInfo = item.count ? item.count + ' matches' : '';
      var timeInfo = item.time ? ' · ' + item.time + 's' : '';

      // Build tag chips from saved match details
      var tagsHtml = '';
      if (item.tags && item.tags.length > 0) {
        tagsHtml = '<div class="vs-history-tags">';
        $.each(item.tags, function (j, tag) {
          var scoreNum = parseFloat(tag.score) || 0;
          var scoreClass = scoreNum >= 80 ? 'vs-htag-good' : scoreNum >= 60 ? 'vs-htag-ok' : 'vs-htag-low';
          tagsHtml += '<span class="vs-history-tag ' + scoreClass + '" title="' + esc(tag.product) + ' — ' + scoreNum.toFixed(0) + '%">' +
            esc(tag.tag) + ' <small>' + scoreNum.toFixed(0) + '%</small>' +
          '</span>';
        });
        tagsHtml += '</div>';
      }

      var el =
        '<div class="vs-history-item" data-history-index="' + i + '" data-thumb="' + item.thumb + '" title="' + item.count + ' results in ' + item.time + 's">' +
          '<img class="vs-history-thumb" src="' + item.thumb + '" alt="Search ' + (i + 1) + '">' +
          '<div class="vs-history-info">' +
            '<div class="vs-history-time">' + ago + '</div>' +
            (resultInfo ? '<div class="vs-history-meta">' + resultInfo + timeInfo + '</div>' : '') +
            tagsHtml +
          '</div>' +
        '</div>';
      $historyList.append(el);
    });
    $historyPanel.show();
  }

  // Click history item → replay stored results (no re-search needed)
  $(document).on("click", ".vs-history-item", function () {
    var thumbSrc = $(this).attr("data-thumb");
    var histIdx  = parseInt($(this).attr("data-history-index"), 10);
    if (!thumbSrc) {
      showAlert("warning", "Search image not available");
      return;
    }

    // Retrieve stored results from localStorage
    var history = [];
    try {
      history = JSON.parse(localStorage.getItem(HISTORY_KEY) || "[]");
    } catch (e) {}

    var entry = history[histIdx];
    if (!entry || !entry.results || entry.results.length === 0) {
      // Fallback: re-search if no stored results
      queryImgSrc = thumbSrc;
      $("#vsUploadPreview").attr("src", thumbSrc).show();
      $(".vs-dropzone-content").hide();
      try {
        fetch(thumbSrc)
          .then(function (res) { return res.blob(); })
          .then(function (blob) {
            var file = new File([blob], "history_search.jpg", { type: "image/jpeg" });
            doSearch(file);
          })
          .catch(function () { showAlert("danger", "Could not load search image"); });
      } catch (e) { showAlert("danger", "Could not load search image"); }
      return;
    }

    // Replay stored results without hitting the API
    queryImgSrc = thumbSrc;
    $("#vsUploadPreview").attr("src", thumbSrc).show();
    $(".vs-dropzone-content").hide();

    // Reset UI
    allResults = entry.results;
    currentSessionId = null;
    votes = {};
    $topContainer.empty();
    $resultsSection.hide();
    $noResults.hide();
    $("#vsShowMoreBtn").hide().data("expanded", false);
    $("#vsOnSaleTag").hide();

    // Update stats banner
    $("#vsStatsCount").text(allResults.length);
    $("#vsStatsTime").text(entry.time || "0");
    $statsBanner.show();

    $uploadSection.hide();
    $newSearchBtn.show();
    $("#vsAgeFilter").show();

    // Render top matches
    var topCount = Math.min(TOP_MATCH_COUNT, allResults.length);
    for (var i = 0; i < topCount; i++) {
      renderTopMatch(allResults[i], i, i === 0);
    }

    // Proactively fetch multi-branch availability for top cards
    for (var j = 0; j < topCount; j++) {
      (function(cardIdx) {
        var r = allResults[cardIdx];
        if (!r || !r.tag_code) return;
        $.ajax({
          url: tagDetailsUrl,
          type: "POST",
          data: { tag_code: r.tag_code },
          dataType: "json",
          success: function(resp) {
            if (resp.status && resp.data) {
              updateInlineBranch(cardIdx, resp.data);
            }
          }
        });
      })(j);
    }

    // Fetch insights + suggestions in a single merged call
    var topMatch = allResults[0];
    if (topMatch) {
      fetchCollectionInsights(topMatch, allResults);
    }

    // Remaining → similar items strip
    var remaining = allResults.slice(topCount);
    if (remaining.length > 0) {
      renderSimilarStrips(remaining, topCount);
    }

    $("#vsOnSaleTag").hide();
    $resultsSection.fadeIn(300);
  });

  function timeAgo(ts) {
    var diff = Math.floor((Date.now() - ts) / 1000);
    if (diff < 60) return "Just now";
    if (diff < 3600) return Math.floor(diff / 60) + "m ago";
    if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
    return Math.floor(diff / 86400) + "d ago";
  }

  // Load history on page init
  loadHistory();

  // ═══════════════════════════════════════════
  // AI Insights Sidebar (collection-level)
  // ═══════════════════════════════════════════

  // ═══════════════════════════════════════════
  // Search Context Insights — 5-Row System
  // ═══════════════════════════════════════════

  var _insightData = null; // cached insight response for modal access
  var _currentGroupBy = 'auto'; // current modal grouping level
  var _currentModalType = 'fns'; // current open modal type for re-fetch

  /**
   * Build group-by selector HTML for insight modals.
   * SV levels: Category, Product, Design, Sub-Design
   */
  function _buildGroupBySelector(currentGroupBy) {
    var levels = [
      { value: 'category', label: 'Category' },
      { value: 'product', label: 'Product' },
      { value: 'design', label: 'Design' },
      { value: 'sub_design', label: 'Sub-Design' }
    ];
    var h = '<div class="vs-im-groupby" style="margin:8px 0 10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
    h += '<label style="font-weight:600;font-size:12px;color:#64748B;white-space:nowrap;"><i class="fa fa-layer-group"></i> Group by:</label>';
    h += '<div class="vs-im-groupby-pills" style="display:flex;gap:4px;flex-wrap:wrap;">';
    for (var i = 0; i < levels.length; i++) {
      var isActive = (levels[i].value === currentGroupBy);
      h += '<button type="button" class="vs-groupby-pill' + (isActive ? ' active' : '') + '" data-group="' + levels[i].value + '" ';
      h += 'style="padding:4px 12px;border-radius:16px;border:1px solid ' + (isActive ? '#4F46E5' : '#CBD5E1') + ';';
      h += 'background:' + (isActive ? '#4F46E5' : '#fff') + ';color:' + (isActive ? '#fff' : '#334155') + ';';
      h += 'font-size:11px;font-weight:600;cursor:pointer;transition:all 0.2s;">';
      h += levels[i].label + '</button>';
    }
    h += '</div></div>';
    return h;
  }

  /**
   * Re-fetch FNS data with a different group_by level and rebuild the modal.
   */
  function _refetchAndRebuildModal(groupByValue, modalType) {
    if (!_insightData || !_lastTopMatch) return;
    _currentGroupBy = groupByValue;

    var $body = $("#vsInsightModalBody");
    $body.css("opacity", "0.5").css("pointer-events", "none");

    var days = getAgeDays();
    var topMatch = _lastTopMatch;
    // FNS-only params — scoped to matched result's context
    var params = { days: days, group_by: groupByValue };
    if (topMatch.category_name) params.category = topMatch.category_name;
    if (topMatch.product_name) params.product_name = topMatch.product_name;
    var collection = topMatch.collection_name || "";
    if (collection) params.collection = collection;

    $.ajax({
      url: proxyAnalyticsUrl,
      type: "POST",
      data: {
        endpoint: "fns-classification",
        params: JSON.stringify(params)
      },
      dataType: "json",
      timeout: 30000,
      success: function (data) {
        console.log("[VS GroupBy] FNS response:", data);
        console.log("[VS GroupBy] categories count:", (data && data.categories) ? data.categories.length : 'N/A');
        console.log("[VS GroupBy] grouped_by:", data ? data.grouped_by : 'N/A');
        // Merge just the FNS result into cached insight data
        if (data && data.categories) {
          _insightData.fns = {
            summary: data.summary,
            categories: data.categories,
            grouped_by: data.grouped_by,
            period_days: data.period_days
          };
        }
        console.log("[VS GroupBy] Rebuilding modal type:", modalType);
        openInsightModal(modalType, _insightData);
        $("#vsInsightModalBody").css("opacity", "1").css("pointer-events", "auto");
      },
      error: function () {
        $body.css("opacity", "1").css("pointer-events", "auto");
      }
    });
  }

  // Delegate click for group-by pill buttons
  $(document).on("click", ".vs-groupby-pill", function () {
    var groupVal = $(this).data("group");
    if (groupVal === _currentGroupBy) return;
    _refetchAndRebuildModal(groupVal, _currentModalType);
  });

  /**
   * Get the max age (days) from the age-period filter.
   * Custom range: uses max input. Preset: parses upper bound.
   */
  function getAgeDays() {
    var sel = $("#vsAgePeriod").val() || "custom";
    if (sel === "custom") {
      return parseInt($("#vsAgeMax").val()) || 90;
    }
    if (sel === "365+") return 730;
    var parts = sel.split("-");
    return parseInt(parts[1]) || 90;
  }

  var _lastTopMatch = null;

  function fetchCollectionInsights(topMatch, allResults) {
    _lastTopMatch = topMatch;
    var $container = $("#vsInsightRows");
    // Show skeleton loader and clear old data immediately (prevents stale data below loader)
    $("#vsInsightRowsLoader").show();
    $container.find(".vs-insight-row, .vs-insights-empty-row, .vs-ai-disclaimer").remove();
    $topContainer.find(".vs-suggestion-card, .vs-suggestion-skeleton").remove();

    // Show skeleton loader in the suggestion card area (below matched result)
    var skeletonHtml =
      '<div class="vs-suggestion-skeleton" style="padding:12px 16px;margin:10px 0;">' +
        '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">' +
          '<div style="width:18px;height:18px;border-radius:50%;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:vs-skeleton-shimmer 1.5s infinite;"></div>' +
          '<div style="width:70px;height:12px;border-radius:4px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:vs-skeleton-shimmer 1.5s infinite;"></div>' +
        '</div>' +
        '<div style="width:90%;height:11px;border-radius:4px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:vs-skeleton-shimmer 1.5s infinite;margin-bottom:8px;"></div>' +
        '<div style="width:75%;height:11px;border-radius:4px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:vs-skeleton-shimmer 1.5s infinite;margin-bottom:8px;"></div>' +
        '<div style="width:60%;height:11px;border-radius:4px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:vs-skeleton-shimmer 1.5s infinite;"></div>' +
      '</div>';
    var $matchCards = $topContainer.find(".vs-match-card");
    if ($matchCards.length > 0) {
      var $card = $($matchCards[0]);
      var $toggle = $card.find(".vs-similar-toggle-btn");
      if ($toggle.length) { $toggle.before(skeletonHtml); }
      else { $card.find(".vs-match-card-body").append(skeletonHtml); }
    }

    var category = topMatch.product_name || topMatch.category_name || "";
    if (!category) {
      renderInsightRows(null);
      return;
    }

    var days = getAgeDays();
    var params = { category: category, days: days };
    var collection = topMatch.collection_name || topMatch.sub_design_name || topMatch.design_name || "";
    if (collection) params.collection = collection;
    // Pass product_name + design_name for supplier/karigar performance
    if (topMatch.product_name) params.product_name = topMatch.product_name;
    if (topMatch.design_name) params.design_name = topMatch.design_name;
    // Pass tag_code + branch for merged suggestions
    if (topMatch.tag_code) params.tag_code = topMatch.tag_code;
    if (topMatch.branch_name) params.branch_name = topMatch.branch_name;
    // Pass similar tag codes from all results
    if (allResults && allResults.length > 1) {
      var tags = [];
      for (var i = 1; i < Math.min(allResults.length, 6); i++) {
        if (allResults[i].tag_code) tags.push(allResults[i].tag_code);
      }
      if (tags.length > 0) params.similar_tags = tags.join(",");
    }

    $.ajax({
      url: proxyAnalyticsUrl,
      type: "POST",
      data: {
        endpoint: "search-context-insights",
        params: JSON.stringify(params)
      },
      dataType: "json",
      timeout: 90000,
      success: function (data) {
        console.log("[VS Insights] Response:", data);
        _insightData = data;
        renderInsightRows(data);
        // Clear skeleton + old suggestion cards before rendering new ones
        $topContainer.find(".vs-suggestion-card, .vs-suggestion-skeleton").remove();
        // Render merged suggestions for the top match
        if (data && data.suggestions && data.suggestions.length > 0) {
          renderSuggestionCard(0, data.suggestions, data.suggest_source || "rule", { tag_code: topMatch.tag_code });
        }
      },
      error: function (xhr, status, err) {
        console.warn("[VS Insights] AJAX error:", status, err, xhr.responseText);
        _insightData = null;
        $topContainer.find(".vs-suggestion-skeleton").remove();
        renderInsightRows(null);
      }
    });
  }

  // ── Age Period filter: show/hide custom inputs ──
  $("#vsAgePeriod").on("change", function () {
    var val = $(this).val();
    if (val === "custom") {
      $("#vsAgeCustom").show();
    } else {
      $("#vsAgeCustom").hide();
      if (val !== "365+") {
        var parts = val.split("-");
        $("#vsAgeMin").val(parts[0]);
        $("#vsAgeMax").val(parts[1]);
      }
    }
    if (_lastTopMatch) fetchCollectionInsights(_lastTopMatch, allResults);
  });

  var _ageInputTimer = null;
  $("#vsAgeMin, #vsAgeMax").on("change input", function () {
    clearTimeout(_ageInputTimer);
    _ageInputTimer = setTimeout(function () {
      if (_lastTopMatch) fetchCollectionInsights(_lastTopMatch, allResults);
    }, 600);
  });

  /**
   * Format INR values in lakhs/crores for compact display.
   */
  function fmtInr(val) {
    val = parseFloat(val) || 0;
    if (val >= 10000000) return "\u20B9" + (val / 10000000).toFixed(1) + "Cr";
    if (val >= 100000) return "\u20B9" + (val / 100000).toFixed(1) + "L";
    if (val >= 1000) return "\u20B9" + (val / 1000).toFixed(1) + "K";
    return "\u20B9" + val.toFixed(0);
  }

  /**
   * Get all FNS category rows — backend already filters by collection.
   */
  function getAllFnsRows(fnsData) {
    if (!fnsData || !fnsData.categories) return [];
    return fnsData.categories;
  }

  /**
   * Compute purchase signal from multiple data sources.
   * Returns: { signal: 'buy'|'watch'|'skip', reason: '...', details: {...} }
   */
  function computePurchaseSignal(data, searchResultCount) {
    if (!data) return { signal: "watch", reason: "Insufficient data", details: {} };

    var deadStock = data.dead_stock || {};
    var availability = data.stock_availability || {};
    var pdv = data.product_design_velocity || {};
    var supplier = data.supplier_performance || {};
    var product = pdv.product || {};
    var design = pdv.design || {};

    // Prefer product-level data for purchase decision
    var totalStock = parseInt(product.stock) || 0;
    var totalSold = parseInt(product.sold) || 0;
    var avgTurnover = totalStock > 0 ? parseFloat((totalSold / totalStock).toFixed(3)) : 0;
    var branchCount = parseInt(product.branches) || (availability.branch_count || 0);
    var deadItems = (deadStock.summary && deadStock.summary.total_items) || 0;
    var stockPieces = availability.total_pieces || 0;

    // If product-level data is missing, fallback to FNS aggregation
    if (!totalSold && !totalStock) {
      var catRows = getAllFnsRows(data.fns || {});
      var fastCount = 0, slowCount = 0, nonMovCount = 0;
      for (var i = 0; i < catRows.length; i++) {
        totalStock += parseInt(catRows[i].in_stock_count) || 0;
        totalSold += parseInt(catRows[i].sold_in_period) || 0;
        if (catRows[i].classification === "Fast") fastCount++;
        else if (catRows[i].classification === "Slow") slowCount++;
        else nonMovCount++;
      }
      avgTurnover = totalStock > 0 ? parseFloat((totalSold / totalStock).toFixed(3)) : 0;
    }

    // Karigar best sell-through (enriches purchase signal)
    var bestKarigar = (supplier.summary && supplier.summary.best_karigar) || "";
    var bestSt = parseFloat(supplier.summary && supplier.summary.best_sell_through) || 0;

    // Decision logic — product-level
    var signal, reason;
    if (totalSold > 0 && avgTurnover > 0.2) {
      if (totalStock < totalSold * 2) {
        signal = "buy";
        reason = totalSold + " sold, only " + totalStock + " left across " + branchCount + " branches";
      } else {
        signal = "watch";
        reason = totalSold + " sold but " + totalStock + " still in stock. Adequate supply.";
      }
    } else if (deadItems > 5 && totalSold === 0) {
      signal = "skip";
      reason = deadItems + " dead stock items. No sales in 90 days.";
    } else if (totalSold > 0) {
      signal = "watch";
      reason = totalSold + " sold, " + totalStock + " in stock. Moderate demand.";
    } else {
      signal = "skip";
      reason = "No sales recorded for this pattern in 90 days.";
    }

    // Append karigar context if available
    if (bestKarigar && bestSt > 0) {
      reason += " Best supplier: " + bestKarigar + " (" + bestSt + "% sell-through).";
    }

    return {
      signal: signal,
      reason: reason,
      details: {
        totalStock: totalStock,
        totalSold: totalSold,
        avgTurnover: avgTurnover,
        deadItems: deadItems,
        stockPieces: stockPieces,
        branchCount: branchCount,
        searchResults: searchResultCount,
        bestKarigar: bestKarigar,
        bestSellThrough: bestSt
      }
    };
  }

  /**
   * Render 5 insight rows in the sidebar from the bundled API response.
   */
  function renderInsightRows(data) {
    var $container = $("#vsInsightRows");
    $("#vsInsightRowsLoader").hide();
    $container.find(".vs-insight-row, .vs-insights-empty-row, .vs-ai-disclaimer").remove();

    if (!data || !data.fns) {
      $container.append(
        '<div class="vs-insights-empty-row">' +
          '<i class="fa fa-lightbulb-o"></i>' +
          '<p>No insights available for this category yet.</p>' +
        '</div>'
      );
      return;
    }

    var catRows = getAllFnsRows(data.fns);
    var purchaseSignal = computePurchaseSignal(data, allResults.length);

    // Row 1: Stock Movement — product-level sell-through (THIS pattern, not the whole collection)
    var pdv = data.product_design_velocity || {};
    var pdvProduct = pdv.product || {};
    var pdvDesign = pdv.design || {};
    var pSold = parseInt(pdvProduct.sold) || 0;
    var pStock = parseInt(pdvProduct.stock) || 0;
    var pBranches = parseInt(pdvProduct.branches) || 0;
    var pTurnover = pStock > 0 ? parseFloat((pSold / pStock).toFixed(3)) : 0;
    var pName = pdvProduct.name || "";

    // Classification: product-level sell-through is most accurate for THIS pattern
    // FNS catRows include ALL products in category (not just the matched one)
    var fnsClass = "Non-Moving", fnsColor = "red", fnsBadge = "non";
    if (pSold > 0 || pStock > 0) {
      // Product-level data available — use sell-through ratio
      if (pSold > 0 && pStock < pSold * 2) {
        fnsClass = "Fast Moving"; fnsColor = "green"; fnsBadge = "fast";
      } else if (pSold > 0) {
        fnsClass = "Slow Moving"; fnsColor = "amber"; fnsBadge = "slow";
      }
      // else: Non-Moving (no sales)
    } else if (catRows.length > 0) {
      // No product-level data — fallback to FNS majority
      var fc = 0, sc = 0, nc = 0;
      for (var i = 0; i < catRows.length; i++) {
        if (catRows[i].classification === "Fast") fc++;
        else if (catRows[i].classification === "Slow") sc++;
        else nc++;
      }
      if (fc > sc && fc > nc) { fnsClass = "Fast Moving"; fnsColor = "green"; fnsBadge = "fast"; }
      else if (sc >= fc && sc >= nc) { fnsClass = "Slow Moving"; fnsColor = "amber"; fnsBadge = "slow"; }
    }

    // Stats: product-level numbers
    var fnsStats = pStock + " in stock \u00B7 " + pSold + " sold";
    if (pBranches > 0) fnsStats += " \u00B7 " + pBranches + " branches";
    if (pTurnover > 0) fnsStats += " \u00B7 " + pTurnover.toFixed(2) + "x";

    $container.append(buildInsightRow(
      fnsColor, "fa-heartbeat", "Stock Movement",
      '<span class="vs-ibadge vs-ibadge-' + fnsBadge + '">' + fnsClass + '</span>',
      fnsStats,
      "fns"
    ));

    // Row 2: Purchase Decision — enriched with karigar context
    var psColor = purchaseSignal.signal === "buy" ? "green" : (purchaseSignal.signal === "watch" ? "amber" : "red");
    var psReason = purchaseSignal.reason;
    if (psReason.length > 150) psReason = psReason.substring(0, 147) + "...";
    $container.append(buildInsightRow(
      psColor, "fa-shopping-cart", "Purchase Decision",
      '<span class="vs-ibadge vs-ibadge-' + purchaseSignal.signal + '">' + purchaseSignal.signal.toUpperCase() + '</span>',
      psReason,
      "purchase"
    ));

    // Row 3: Fast Movers — pattern match (product + design + branches)
    var matchedDesign = pdvDesign || {};
    var similarDesigns = pdv.similar_designs || [];
    var designSold = parseInt(matchedDesign.sold) || 0;
    var designStock = parseInt(matchedDesign.stock) || 0;
    var designName = esc(matchedDesign.design || "");
    var velStats = "";
    if (designName || pName) {
      // Pattern-level: "NAGAS Jhumkas — 12 sold, 8 in stock across 5 branches"
      var patternLabel = designName || "";
      if (pName && designName) patternLabel = designName + " " + esc(pName);
      else if (pName) patternLabel = esc(pName);
      velStats = patternLabel + " \u2014 " + designSold + " sold, " + designStock + " in stock";
      if (pBranches > 0) velStats += " across " + pBranches + " branches";
    } else {
      // Fallback to FNS fast combos
      var fnsCategories = (data.fns || {}).categories || [];
      var fastCombos = [];
      for (var fi = 0; fi < fnsCategories.length; fi++) {
        if (fnsCategories[fi].classification === "Fast") fastCombos.push(fnsCategories[fi]);
      }
      if (fastCombos.length > 0) {
        velStats = fastCombos.length + " fast combos \u00B7 Top: " + esc(fastCombos[0].category || "") + " (" + (fastCombos[0].sold_count || 0) + " sold)";
      } else {
        velStats = "No fast-moving patterns found";
      }
    }
    var velColor = designSold >= 5 ? "green" : (designSold > 0 ? "indigo" : "red");
    $container.append(buildInsightRow(
      velColor, "fa-rocket", "Fast Movers",
      "",
      velStats,
      "velocity"
    ));

    // Row 4: Transfer Opportunities — items selling at one branch but stale at another
    var transfers = data.transfer_candidates || {};
    var transferItems = transfers.transfers || [];
    var transferStats = transferItems.length + " transfer opportunities";
    if (transferItems.length > 0) {
      var top = transferItems[0];
      transferStats += " \u00B7 " + esc(top.product_name || "") + ": " + esc(top.from_branch || "") + " → " + esc(top.to_branch || "");
    }
    var transferColor = transferItems.length > 5 ? "amber" : (transferItems.length > 0 ? "indigo" : "green");
    $container.append(buildInsightRow(
      transferColor, "fa-truck", "Transfer Opportunities",
      "",
      transferStats,
      "transfer"
    ));

    // Row 5: Dead Stock (>90 days)
    var deadSummary = (data.dead_stock && data.dead_stock.summary) || {};
    var deadCount = deadSummary.total_items || 0;
    var deadValue = deadSummary.total_value || 0;
    var deadAvgAge = deadSummary.avg_age_days || 0;
    var deadColor = deadCount > 20 ? "red" : (deadCount > 5 ? "amber" : "green");
    $container.append(buildInsightRow(
      deadColor, "fa-exclamation-triangle", "Dead Stock (>90d)",
      "",
      deadCount + " items \u00B7 " + fmtInr(deadValue) + " locked \u00B7 Avg " + deadAvgAge + " days",
      "dead_stock"
    ));

    // AI Disclaimer
    $container.append(
      '<div class="vs-ai-disclaimer">' +
        '<i class="fa fa-info-circle"></i> ' +
        'AI-generated insights may contain inaccuracies. Verify critical data before making business decisions.' +
      '</div>'
    );
  }

  /**
   * Build a single insight row HTML string.
   */
  function buildInsightRow(color, icon, title, badgeHtml, stats, type) {
    return '<div class="vs-insight-row vs-ir-' + color + '" data-insight-type="' + type + '">' +
      '<div class="vs-insight-row-icon"><i class="fa ' + icon + '"></i></div>' +
      '<div class="vs-insight-row-content">' +
        '<div class="vs-insight-row-title">' + esc(title) + ' ' + badgeHtml + '</div>' +
        '<div class="vs-insight-row-stats">' + stats + '</div>' +
      '</div>' +
      '<button class="vs-insight-detail-btn" onclick="event.stopPropagation();">Details \u2192</button>' +
    '</div>';
  }

  // Click handler for insight rows — open detail modal
  $(document).on("click", ".vs-insight-row, .vs-insight-detail-btn", function (e) {
    e.stopPropagation();
    var type = $(this).closest(".vs-insight-row").data("insight-type");
    if (type && _insightData) {
      openInsightModal(type, _insightData);
    }
  });

  /**
   * Generate clickable thumbnail HTML for insight modal tables.
   */
  function _thumbHtml(imageUrl) {
    if (!imageUrl) return '';
    var fullUrl = (imageUrl.indexOf('http') === 0 || imageUrl.indexOf('/') === 0)
      ? imageUrl
      : _base + 'assets/img/tag/' + imageUrl;
    return '<img src="' + fullUrl + '" class="vs-thumb-img" ' +
           'data-full-src="' + fullUrl + '" ' +
           'style="width:30px;height:30px;object-fit:cover;border-radius:4px;cursor:pointer;margin-right:6px;vertical-align:middle;" ' +
           'onerror="this.style.display=\'none\'" />';
  }

  // Full-image modal handler — click thumbnail to enlarge
  $(document).on("click", ".vs-thumb-img", function (e) {
    e.stopPropagation();
    var src = $(this).data("full-src");
    if (!src) return;
    var $modal = $("#vsFullImageModal");
    if (!$modal.length) {
      $("body").append(
        '<div class="modal fade" id="vsFullImageModal" tabindex="-1">' +
          '<div class="modal-dialog modal-lg modal-dialog-centered">' +
            '<div class="modal-content" style="background:transparent;border:none;box-shadow:none;">' +
              '<div class="modal-body text-center p-0">' +
                '<img id="vsFullImageSrc" src="" style="max-width:100%;max-height:80vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);" />' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
      $modal = $("#vsFullImageModal");
    }
    $("#vsFullImageSrc").attr("src", src);
    $modal.modal("show");
  });

  /**
   * Open the insight detail modal with content specific to the insight type.
   */
  function openInsightModal(type, data) {
    var $title = $("#vsInsightModalTitle");
    var $body = $("#vsInsightModalBody");
    var $header = $("#vsInsightModalHeader");
    var category = (data.context && data.context.category) || "";
    var collection = (data.context && data.context.collection) || "";
    var displayName = collection || category;
    var html = "";
    _currentModalType = type; // track for group-by re-fetch

    switch (type) {
      case "fns":
        var fnsTitle = (data.context && data.context.product_name) || displayName;
        $title.html('<i class="fa fa-heartbeat"></i> Stock Movement Classification — ' + esc(fnsTitle));
        $header.css("border-bottom-color", "#10B981");
        html = buildFnsModal(data);
        break;
      case "purchase":
        var purchTitle = (data.context && data.context.product_name) || displayName;
        $title.html('<i class="fa fa-shopping-cart"></i> Purchase Decision — ' + esc(purchTitle));
        $header.css("border-bottom-color", "#4F46E5");
        html = buildPurchaseModal(data);
        break;
      case "velocity":
        var velTitle = (data.context && data.context.product_name) || displayName;
        $title.html('<i class="fa fa-rocket"></i> Fast Movers — ' + esc(velTitle));
        $header.css("border-bottom-color", "#4F46E5");
        html = buildFastMoversModal(data);
        break;
      case "transfer":
        $title.html('<i class="fa fa-truck"></i> Branch Transfer Opportunities');
        $header.css("border-bottom-color", "#F59E0B");
        html = buildTransferModal(data);
        break;
      case "dead_stock":
        $title.html('<i class="fa fa-exclamation-triangle"></i> Dead Stock Tracker (>90 days)');
        $header.css("border-bottom-color", "#EF4444");
        html = buildDeadStockModal(data);
        break;
    }

    $body.html(html);
    $("#vsInsightDetailModal").modal("show");
  }

  /**
   * FNS Classification modal — branch × collection/category table.
   */
  function buildFnsModal(data) {
    var fns = data.fns || {};
    var categories = fns.categories || [];
    var category = (data.context && data.context.category) || "";
    var collection = (data.context && data.context.collection) || "";
    var isCollectionGrouped = (fns.grouped_by === "collection");
    var groupLabelMap = { collection: 'Collection', category: 'Category', product: 'Product', design: 'Design', sub_design: 'Sub-Design' };
    var groupLabel = groupLabelMap[fns.grouped_by] || (isCollectionGrouped ? 'Collection' : 'Category');
    var h = '';

    // Backend already filters by collection — use all returned data
    var filtered = categories;

    // Compute summary from filtered data
    var totalItems = filtered.length;
    var fastItems = 0, slowItems = 0, nonItems = 0;
    for (var s = 0; s < filtered.length; s++) {
      if (filtered[s].classification === "Fast") fastItems++;
      else if (filtered[s].classification === "Slow") slowItems++;
      else nonItems++;
    }
    var fastPct = totalItems > 0 ? Math.round(fastItems / totalItems * 100) : 0;
    var slowPct = totalItems > 0 ? Math.round(slowItems / totalItems * 100) : 0;
    var nonPct = totalItems > 0 ? Math.round(nonItems / totalItems * 100) : 0;

    // Summary strip
    h += '<div class="vs-im-summary">';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + totalItems + '</div><div class="vs-im-stat-label">Branches</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value" style="color:#10B981;">' + fastItems + '</div><div class="vs-im-stat-label">Fast Moving (' + fastPct + '%)</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value" style="color:#F59E0B;">' + slowItems + '</div><div class="vs-im-stat-label">Slow Moving (' + slowPct + '%)</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value" style="color:#EF4444;">' + nonItems + '</div><div class="vs-im-stat-label">Non-Moving (' + nonPct + '%)</div></div>';
    h += '</div>';

    // Group-by pill selector
    h += _buildGroupBySelector(fns.grouped_by || _currentGroupBy);

    // Context indicator — show both category AND product level
    var productName = (data.context && data.context.product_name) || "";
    if (category && productName) {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing movement for product <strong>' + esc(productName) + '</strong> in category <strong>' + esc(category) + '</strong> across all branches</div>';
    } else if (collection && productName) {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing movement for product <strong>' + esc(productName) + '</strong> in collection <strong>' + esc(collection) + '</strong> across all branches</div>';
    } else if (category) {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing movement for category <strong>' + esc(category) + '</strong> across all branches</div>';
    } else if (collection) {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing movement for collection <strong>' + esc(collection) + '</strong> across all branches</div>';
    } else {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing data for <strong>' + esc(category || collection) + '</strong></div>';
    }

    // Table
    h += '<div class="vs-im-section-title"><i class="fa fa-list"></i> Branch-wise Classification</div>';
    h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
    h += '<th>Branch</th><th>' + groupLabel + '</th><th>Class</th><th>Stock</th><th>Sold</th><th>Turnover</th><th>Avg Age</th><th>Holding Cost</th>';
    h += '</tr></thead><tbody>';
    var totStock = 0, totSold = 0, totHolding = 0;
    for (var i = 0; i < Math.min(filtered.length, 50); i++) {
      var c = filtered[i];
      var cls = c.classification || "Unknown";
      var badgeCls = cls === "Fast" ? "fast" : (cls === "Slow" ? "slow" : "non");
      var groupValue = isCollectionGrouped ? (c.collection_name || c.category || "") : (c.category || "");
      var rowStock = parseInt(c.in_stock_count) || 0;
      var rowSold = parseInt(c.sold_in_period) || 0;
      var rowHolding = parseFloat(c.holding_cost) || 0;
      totStock += rowStock; totSold += rowSold; totHolding += rowHolding;
      h += '<tr>';
      h += '<td>' + esc(c.branch_name || "") + '</td>';
      h += '<td>' + esc(groupValue) + '</td>';
      h += '<td><span class="vs-ibadge vs-ibadge-' + badgeCls + '">' + cls + '</span></td>';
      h += '<td>' + rowStock + '</td>';
      h += '<td>' + rowSold + '</td>';
      h += '<td>' + (c.turnover_rate || 0).toFixed(3) + '</td>';
      h += '<td>' + (c.avg_age_days || 0) + ' days</td>';
      h += '<td>' + fmtInr(rowHolding) + '</td>';
      h += '</tr>';
    }
    h += '</tbody><tfoot><tr style="font-weight:700;background:#f1f5f9;">';
    h += '<td colspan="3">Total</td>';
    h += '<td>' + totStock + '</td>';
    h += '<td>' + totSold + '</td>';
    h += '<td></td><td></td>';
    h += '<td>' + fmtInr(totHolding) + '</td>';
    h += '</tr></tfoot></table></div>';
    return h;
  }

  /**
   * Purchase Decision modal — composite signal + supplier breakdown.
   */
  function buildPurchaseModal(data) {
    var ps = computePurchaseSignal(data, allResults.length);
    var d = ps.details;
    var h = '';

    // Signal banner
    h += '<div class="vs-im-signal vs-im-signal-' + ps.signal + '">';
    h += '<div class="vs-im-signal-text">' + ps.signal.toUpperCase() + '</div>';
    h += '<div class="vs-im-signal-reason">' + esc(ps.reason) + '</div>';
    h += '</div>';

    // KPI summary
    h += '<div class="vs-im-summary">';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + (d.totalSold || 0) + '</div><div class="vs-im-stat-label">Sold (90 days)</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + (d.totalStock || 0) + '</div><div class="vs-im-stat-label">In Stock</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + (d.branchCount || 0) + '</div><div class="vs-im-stat-label">Branches</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + (d.deadItems || 0) + '</div><div class="vs-im-stat-label">Dead Stock</div></div>';
    h += '</div>';


    // Supplier / Karigar Performance table
    var supplier = data.supplier_performance || {};
    var karigars = supplier.karigars || [];
    if (karigars.length > 0) {
      h += '<div class="vs-im-section-title"><i class="fa fa-users"></i> Supplier (Karigar) Performance</div>';
      h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
      h += '<th>Karigar</th><th>Supplied</th><th>Sold</th><th>In Stock</th><th>Sell-Through</th><th>Avg Days</th><th>Revenue</th>';
      h += '</tr></thead><tbody>';
      var kTotSupp = 0, kTotSold = 0, kTotInStock = 0, kTotRev = 0;
      for (var i = 0; i < Math.min(karigars.length, 15); i++) {
        var k = karigars[i];
        var stPct = parseFloat(k.sell_through_pct) || 0;
        var stClass = stPct >= 60 ? 'vs-ibadge-fast' : (stPct >= 30 ? 'vs-ibadge-slow' : 'vs-ibadge-non');
        var kSupp = parseInt(k.total_supplied) || 0;
        var kSold = parseInt(k.sold) || 0;
        var kInSt = parseInt(k.in_stock) || 0;
        var kRev = parseFloat(k.revenue) || 0;
        kTotSupp += kSupp; kTotSold += kSold; kTotInStock += kInSt; kTotRev += kRev;
        h += '<tr>';
        h += '<td>' + esc(k.karigar_name || '') + ' <small style="color:#999;">' + esc(k.karigar_code || '') + '</small></td>';
        h += '<td>' + kSupp + '</td>';
        h += '<td>' + kSold + '</td>';
        h += '<td>' + kInSt + '</td>';
        h += '<td><span class="vs-ibadge ' + stClass + '">' + stPct.toFixed(1) + '%</span></td>';
        h += '<td>' + (k.avg_days_to_sell || '-') + '</td>';
        h += '<td>' + fmtInr(kRev) + '</td>';
        h += '</tr>';
      }
      h += '</tbody><tfoot><tr style="font-weight:700;background:#f1f5f9;">';
      h += '<td>Total</td>';
      h += '<td>' + kTotSupp + '</td>';
      h += '<td>' + kTotSold + '</td>';
      h += '<td>' + kTotInStock + '</td>';
      h += '<td></td><td></td>';
      h += '<td>' + fmtInr(kTotRev) + '</td>';
      h += '</tr></tfoot></table></div>';
    }

    // Branch stock availability
    var avail = data.stock_availability || {};
    var branches = avail.branches || [];
    var collection = (data.context && data.context.collection) || "";
    var productName = (data.context && data.context.product_name) || "";
    var category = (data.context && data.context.category) || "";
    if (branches.length > 0) {
      if (category && productName) {
        h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing stock for product <strong>' + esc(productName) + '</strong> in category <strong>' + esc(category) + '</strong></div>';
      } else if (collection && productName) {
        h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing stock for product <strong>' + esc(productName) + '</strong> in collection <strong>' + esc(collection) + '</strong></div>';
      } else if (category) {
        h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing stock for category <strong>' + esc(category) + '</strong></div>';
      } else if (collection) {
        h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing stock for collection <strong>' + esc(collection) + '</strong></div>';
      }
      h += '<div class="vs-im-section-title"><i class="fa fa-building"></i> Stock Availability by Branch</div>';
      h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
      h += '<th>Branch</th><th>Pieces</th><th>Weight (g)</th><th>Value</th>';
      h += '</tr></thead><tbody>';
      var bTotPcs = 0, bTotWt = 0, bTotVal = 0;
      for (var i = 0; i < Math.min(branches.length, 20); i++) {
        var b = branches[i];
        var bPcs = parseInt(b.total_pieces) || 0;
        var bWt = parseFloat(b.total_weight) || 0;
        var bVal = parseFloat(b.total_value) || 0;
        bTotPcs += bPcs; bTotWt += bWt; bTotVal += bVal;
        h += '<tr><td>' + esc(b.branch_name || "") + '</td>';
        h += '<td>' + bPcs + '</td>';
        h += '<td>' + bWt.toFixed(3) + '</td>';
        h += '<td>' + fmtInr(bVal) + '</td></tr>';
      }
      h += '</tbody><tfoot><tr style="font-weight:700;background:#f1f5f9;">';
      h += '<td>Total</td>';
      h += '<td>' + bTotPcs + '</td>';
      h += '<td>' + bTotWt.toFixed(3) + '</td>';
      h += '<td>' + fmtInr(bTotVal) + '</td>';
      h += '</tr></tfoot></table></div>';
    }

    // Recommendation — product-level
    var rec = "";
    if (ps.signal === "buy") {
      rec = '<i class="fa fa-check-circle"></i> <strong>Recommendation:</strong> Good to purchase. ' +
        'This pattern has strong sell-through.';
      if (d.bestKarigar) rec += ' Prefer supplier <strong>' + esc(d.bestKarigar) + '</strong> (' + d.bestSellThrough + '% sell-through).';
      rec += ' Focus on branches with low stock.';
    } else if (ps.signal === "watch") {
      rec = '<i class="fa fa-eye"></i> <strong>Recommendation:</strong> Monitor for 2-4 weeks. ' +
        'Demand exists but stock is adequate. Check if sell-through improves.';
    } else {
      rec = '<i class="fa fa-ban"></i> <strong>Recommendation:</strong> Avoid purchasing. ' +
        'Existing stock is not moving. Consider promotions to clear dead stock first.';
    }
    h += '<div class="vs-im-recommendation">' + rec + '</div>';

    return h;
  }

  /**
   * Fast Movers modal — product × design combos from FNS Fast classification.
   */
  function buildFastMoversModal(data) {
    var fns = data.fns || {};
    var categories = fns.categories || [];
    var h = '';
    var fastItems = [];
    for (var i = 0; i < categories.length; i++) {
      if (categories[i].classification === "Fast") fastItems.push(categories[i]);
    }
    fastItems.sort(function(a, b) {
      return (parseInt(b.sold_count) || 0) - (parseInt(a.sold_count) || 0);
    });
    var totalFastSold = 0;
    for (var j = 0; j < fastItems.length; j++) totalFastSold += parseInt(fastItems[j].sold_count) || 0;

    h += '<div class="vs-im-summary">';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + fastItems.length + '</div><div class="vs-im-stat-label">Fast Combos</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + totalFastSold + '</div><div class="vs-im-stat-label">Total Sold (90d)</div></div>';
    h += '</div>';

    // Group-by pill selector
    var fnsGroupBy = (data.fns && data.fns.grouped_by) || _currentGroupBy;
    h += _buildGroupBySelector(fnsGroupBy);

    h += '<div class="vs-im-section-title"><i class="fa fa-rocket"></i> Fast-Moving Product Combos</div>';
    h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
    h += '<th>Category</th><th>Product</th><th>Branch</th><th>Sold</th><th>Stock</th><th>Turnover</th>';
    h += '</tr></thead><tbody>';
    for (var k = 0; k < Math.min(fastItems.length, 30); k++) {
      var fi = fastItems[k];
      h += '<tr>';
      h += '<td>' + esc(fi.category || fi.collection_name || "") + '</td>';
      h += '<td>' + esc(fi.product_name || "") + '</td>';
      h += '<td>' + esc(fi.branch_name || "") + '</td>';
      h += '<td>' + (fi.sold_count || 0) + '</td>';
      h += '<td>' + (fi.stock_count || 0) + '</td>';
      h += '<td>' + (parseFloat(fi.turnover_rate) || 0).toFixed(3) + '</td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';
    if (fastItems.length === 0) {
      h += '<div class="vs-im-context"><i class="fa fa-info-circle"></i> No fast-moving combos found in the last 90 days.</div>';
    }

    // PDV: Similar designs — sorted by fastest selling, enriched with visual similarity
    var pdv = data.product_design_velocity || {};
    var similarDesigns = pdv.similar_designs || [];
    if (similarDesigns.length > 0) {
      // Build visual match score lookup from allResults
      var visualScores = {};
      for (var vi = 0; vi < allResults.length; vi++) {
        var vr = allResults[vi];
        if (vr.design_name) visualScores[vr.design_name] = parseFloat(vr.score) || 0;
        if (vr.tag_code) visualScores[vr.tag_code] = parseFloat(vr.score) || 0;
      }

      // Sort by sold descending (fastest sellers first)
      var sorted = similarDesigns.slice().sort(function (a, b) {
        return (parseInt(b.sold) || 0) - (parseInt(a.sold) || 0);
      });

      h += '<div class="vs-im-section-title"><i class="fa fa-rocket"></i> Similar Designs — Fastest Selling</div>';
      h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
      h += '<th style="width:42px;"></th><th>Design</th><th>Sold</th><th>In Stock</th><th>Revenue</th><th>Match</th><th>Status</th>';
      h += '</tr></thead><tbody>';
      for (var d = 0; d < Math.min(sorted.length, 15); d++) {
        var sd = sorted[d];
        var sdStatus = sd.status || 'slow';
        var sdBadge = sdStatus === 'fast' ? 'vs-ibadge-fast' : (sdStatus === 'slow' ? 'vs-ibadge-slow' : 'vs-ibadge-non');
        var sdImg = _thumbHtml(sd.image_url);
        var vScore = visualScores[sd.design] || visualScores[sd.tag_code] || 0;
        var vScoreHtml = vScore > 0 ? '<span style="font-size:10px;color:#6366F1;font-weight:700;">' + vScore.toFixed(0) + '%</span>' : '<span style="color:#CBD5E1;font-size:10px;">—</span>';
        h += '<tr>';
        h += '<td>' + (sdImg || '<span style="display:inline-block;width:30px;height:30px;border-radius:4px;background:#F1F5F9;text-align:center;line-height:30px;color:#94A3B8;font-size:12px;"><i class="fa fa-image"></i></span>') + '</td>';
        h += '<td>' + esc(sd.design || '-') + '</td>';
        h += '<td>' + (sd.sold || 0) + '</td>';
        h += '<td>' + (sd.stock || 0) + '</td>';
        h += '<td>' + fmtInr(sd.revenue || 0) + '</td>';
        h += '<td>' + vScoreHtml + '</td>';
        h += '<td><span class="vs-ibadge ' + sdBadge + '">' + sdStatus.toUpperCase() + '</span></td>';
        h += '</tr>';
      }
      h += '</tbody></table></div>';
    }

    return h;
  }

  /**
   * Transfer Opportunities modal — items selling at one branch but stale at another.
   */
  function buildTransferModal(data) {
    var transfers = data.transfer_candidates || {};
    var items = transfers.transfers || [];
    var h = '';

    h += '<div class="vs-im-summary">';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + items.length + '</div><div class="vs-im-stat-label">Transfer Candidates</div></div>';
    if (transfers.summary) {
      h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + fmtInr(transfers.summary.total_stale_value || 0) + '</div><div class="vs-im-stat-label">Stale Value at Risk</div></div>';
      h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + fmtInr(transfers.summary.total_demand_revenue || 0) + '</div><div class="vs-im-stat-label">Demand Revenue</div></div>';
    }
    h += '</div>';

    if (items.length > 0) {
      h += '<div class="vs-im-section-title"><i class="fa fa-truck"></i> Recommended Transfers</div>';
      h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
      h += '<th>Product</th><th>Category</th><th>From Branch</th><th>Stale Pcs</th><th>Age (days)</th><th>To Branch</th><th>Demand Sold</th><th>Suggested Qty</th>';
      h += '</tr></thead><tbody>';
      for (var i = 0; i < Math.min(items.length, 30); i++) {
        var c = items[i];
        h += '<tr>';
        h += '<td>' + esc(c.product_name || "") + '</td>';
        h += '<td>' + esc(c.category || "") + '</td>';
        h += '<td>' + esc(c.from_branch || "") + '</td>';
        h += '<td>' + (c.stale_count || 0) + '</td>';
        h += '<td>' + (c.stale_age_days || 0) + '</td>';
        h += '<td>' + esc(c.to_branch || "") + '</td>';
        h += '<td>' + (c.demand_sold || 0) + '</td>';
        h += '<td>' + (c.transfer_qty || 0) + '</td>';
        h += '</tr>';
      }
      h += '</tbody></table></div>';
    } else {
      h += '<div class="vs-im-context"><i class="fa fa-check-circle" style="color:#10B981;"></i> No transfer opportunities — stock is well-distributed.</div>';
    }
    return h;
  }

  /**
   * Dead Stock modal — items table + category breakdown.
   */
  function buildDeadStockModal(data) {
    var ds = data.dead_stock || {};
    var allItems = ds.items || [];
    var category = (data.context && data.context.category) || "";
    var catLower = category.toLowerCase();
    var h = '';

    // Filter items to matched category
    var items = allItems.filter(function (it) {
      return catLower && (it.category || "").toLowerCase().indexOf(catLower) > -1;
    });
    var isFiltered = items.length > 0;
    if (!isFiltered) items = allItems; // fallback to all if no match

    // Compute filtered summary
    var deadCount = items.length;
    var deadValue = 0, totalAge = 0;
    for (var s = 0; s < items.length; s++) {
      deadValue += parseFloat(items[s].value) || 0;
      totalAge += parseInt(items[s].age_days) || 0;
    }
    var avgAge = deadCount > 0 ? Math.round(totalAge / deadCount) : 0;

    h += '<div class="vs-im-summary">';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value" style="color:#EF4444;">' + deadCount + '</div><div class="vs-im-stat-label">Dead Items</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + fmtInr(deadValue) + '</div><div class="vs-im-stat-label">Capital Locked</div></div>';
    h += '<div class="vs-im-stat"><div class="vs-im-stat-value">' + avgAge + '</div><div class="vs-im-stat-label">Avg Age (days)</div></div>';
    h += '</div>';

    if (isFiltered) {
      h += '<div class="vs-im-context"><i class="fa fa-filter"></i> Showing dead stock for <strong>' + esc(category) + '</strong> only</div>';
    }

    // Item details
    h += '<div class="vs-im-section-title"><i class="fa fa-list"></i> Dead Stock Items' + (isFiltered ? ' — ' + esc(category) : ' (Top 20)') + '</div>';
    h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
    h += '<th>Tag Code</th><th>Product</th><th>Category</th><th>Branch</th><th>Age</th><th>Value</th>';
    h += '</tr></thead><tbody>';
    for (var i = 0; i < Math.min(items.length, 20); i++) {
      var it = items[i];
      h += '<tr>';
      h += '<td>' + esc(it.tag_code || "") + '</td>';
      h += '<td>' + esc(it.product || "") + '</td>';
      h += '<td>' + esc(it.category || "") + '</td>';
      h += '<td>' + esc(it.branch || "") + '</td>';
      h += '<td>' + (it.age_days || 0) + ' days</td>';
      h += '<td>' + fmtInr(it.value || 0) + '</td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';

    // Stock availability
    var avail = data.stock_availability || {};
    var availBranches = avail.branches || [];
    if (availBranches.length > 0) {
      h += '<div class="vs-im-section-title"><i class="fa fa-building"></i> Current Stock Availability</div>';
      h += '<div style="overflow-x:auto;"><table class="vs-im-table"><thead><tr>';
      h += '<th>Branch</th><th>Pieces</th><th>Weight (g)</th><th>Value</th>';
      h += '</tr></thead><tbody>';
      for (var k = 0; k < Math.min(availBranches.length, 15); k++) {
        var ab = availBranches[k];
        h += '<tr><td>' + esc(ab.branch_name || "") + '</td>';
        h += '<td>' + (ab.total_pieces || 0) + '</td>';
        h += '<td>' + (parseFloat(ab.total_weight) || 0).toFixed(3) + '</td>';
        h += '<td>' + fmtInr(ab.total_value || 0) + '</td></tr>';
      }
      h += '</tbody></table></div>';
    }

    return h;
  }

  // ═══════════════════════════════════════════
  // WinPixel AI Suggestions (inline per-match)
  // ═══════════════════════════════════════════
  function fetchSuggestion(result, matchIndex) {
    // Build params from search result context
    var params = { tag_code: result.tag_code || "" };
    if (result.product_name) params.category = result.product_name;
    if (result.collection_name) params.collection_name = result.collection_name;
    if (result.branch_name) params.branch_name = result.branch_name;

    $.ajax({
      url: proxyAnalyticsUrl,
      type: "POST",
      data: {
        endpoint: "suggest",
        params: JSON.stringify(params)
      },
      dataType: "json",
      success: function (data) {
        if (data && data.suggestions && data.suggestions.length > 0) {
          renderSuggestionCard(matchIndex, data.suggestions, data.source || "rule", params);
        }
      },
      error: function () {
        // Silent fail — suggestions are optional
      }
    });
  }

  function renderSuggestionCard(matchIndex, suggestions, source, context) {
    var aiIcon = source === "ai" ? "fa-magic" : "fa-lightbulb-o";
    var aiLabel = source === "ai" ? "AI Insight" : "Insight";

    var html =
      '<div class="vs-suggestion-card" style="animation: vs-fadeInUp 0.4s ease both;">' +
        '<div class="vs-suggestion-header">' +
          '<span class="vs-suggestion-icon"><i class="fa ' + aiIcon + '"></i></span>' +
          '<span class="vs-suggestion-label">' + aiLabel + '</span>' +
        '</div>' +
        '<div class="vs-suggestion-body">';

    for (var i = 0; i < suggestions.length; i++) {
      html += '<p class="vs-suggestion-text">' + esc(suggestions[i]) + '</p>';
    }

    html += '</div></div>';

    // Insert before the similar items toggle inside this match card
    var $matchCards = $topContainer.find(".vs-match-card");
    if ($matchCards.length > matchIndex) {
      var $card = $($matchCards[matchIndex]);
      var $toggle = $card.find(".vs-similar-toggle-btn");
      if ($toggle.length) {
        $toggle.before(html);
      } else {
        $card.find(".vs-match-card-body").append(html);
      }
    } else {
      $topContainer.append(html);
    }
  }

});
