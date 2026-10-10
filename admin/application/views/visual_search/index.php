<!-- Content Wrapper. Contains page content -->
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/visual_search.css">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="content-wrapper">
  <!-- Full-Page Progress Overlay — Image Matching Animation -->
  <div id="vsSearchOverlay" class="vs-fullpage-overlay" style="display:none;">
    <div class="vs-match-anim-container">
      <!-- Matching Visual: Two image frames with scan line -->
      <div class="vs-match-anim-frames">
        <div class="vs-match-anim-frame vs-match-anim-left">
          <img id="vsMatchAnimSrc" src="" alt="Your Image">
          <div class="vs-match-anim-label">YOUR IMAGE</div>
        </div>
        <div class="vs-match-anim-center">
          <div class="vs-match-anim-scan-ring">
            <svg viewBox="0 0 60 60">
              <circle cx="30" cy="30" r="27" fill="none" stroke="#E2E8F0" stroke-width="3"/>
              <circle cx="30" cy="30" r="27" fill="none" stroke="#4F46E5" stroke-width="3"
                      stroke-dasharray="170" stroke-dashoffset="170" stroke-linecap="round"
                      class="vs-match-anim-ring-fill"/>
            </svg>
            <i class="fa fa-search vs-match-anim-icon"></i>
          </div>
          <div class="vs-match-anim-vs">MATCHING</div>
        </div>
        <div class="vs-match-anim-frame vs-match-anim-right">
          <div class="vs-match-anim-skeleton">
            <div class="vs-match-anim-skeleton-img"></div>
          </div>
          <div class="vs-match-anim-label">FINDING MATCH</div>
        </div>
      </div>
      <!-- Progress bar -->
      <div class="vs-match-anim-progress">
        <div class="vs-match-anim-progress-bar" id="vsMatchAnimBar"></div>
      </div>
      <p class="vs-match-anim-text" id="vsProgressText">Searching across inventory...</p>
    </div>
  </div>

  <!-- Content Header -->
  <section class="content-header vs-header">
    <div class="vs-brand-header">
      <div class="vs-brand-left">
        <div class="vs-brand-logo"><i class="fa fa-eye"></i></div>
        <div class="vs-brand-text">
          <h1>Visual Search</h1>
        </div>
      </div>
      <div class="vs-brand-right">
        <div class="vs-age-filter" id="vsAgeFilter" style="display:none;">
          <select id="vsAgePeriod" class="form-control vs-age-select">
            <option value="0-30">0 – 30 days</option>
            <option value="31-90">31 – 90 days</option>
            <option value="91-120">91 – 120 days</option>
            <option value="121-180">121 – 180 days</option>
            <option value="181-240">181 – 240 days</option>
            <option value="241-365">241 – 365 days</option>
            <option value="365+">365+ days</option>
            <option value="custom" selected>Custom Range</option>
          </select>
          <span id="vsAgeCustom" class="vs-age-custom">
            <input type="number" id="vsAgeMin" class="form-control vs-age-input" value="0" min="0" max="9999" title="Min days" />
            <span class="vs-age-dash">–</span>
            <input type="number" id="vsAgeMax" class="form-control vs-age-input" value="90" min="0" max="9999" title="Max days" />
            <span class="vs-age-unit">days</span>
          </span>
        </div>
        <button class="btn btn-primary vs-new-search-btn" id="vsNewSearchBtn" style="display:none;">
          <i class="fa fa-plus"></i> New Search
        </button>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">

    <!-- ═══════════════════════════════════════
         Upload Section (visible initially)
         ═══════════════════════════════════════ -->
    <div id="vsUploadSection">
      <div class="row">
        <!-- Left: Upload Wizard -->
        <div class="col-md-8 col-sm-7 col-xs-12">
          <div class="vs-upload-box">
            <input type="file" id="vs_file_input" accept="image/*" style="display:none;">

            <!-- Upload Mode: Dropzone -->
            <div id="vsUploadMode">
              <div class="vs-dropzone" id="vsDropzone" role="button" tabindex="0" aria-label="Drag and drop a jewellery image or click to browse">
                <div class="vs-dropzone-content" id="vsDropzoneContent">
                  <div class="vs-dropzone-icon-wrap">
                    <i class="fa fa-cloud-upload vs-dropzone-icon"></i>
                    <div class="vs-dropzone-pulse"></div>
                  </div>
                  <h4>Drag & drop a jewellery image here</h4>
                  <p class="text-muted">or use the options below</p>
                  <p class="vs-format-hint"><i class="fa fa-file-image-o"></i> JPG, PNG, WebP, BMP — Max 10MB</p>
                </div>
              </div>

              <!-- Equal-weight Browse + Camera buttons -->
              <div class="row" style="margin-top:14px;">
                <div class="col-sm-6 col-xs-12" style="margin-bottom:8px;">
                  <button class="btn vs-cta-upload btn-block" onclick="document.getElementById('vs_file_input').click(); return false;">
                    <i class="fa fa-folder-open"></i> Browse Files
                  </button>
                </div>
                <div class="col-sm-6 col-xs-12">
                  <button class="btn btn-default btn-block vs-camera-btn" id="vsCameraBtn">
                    <i class="fa fa-camera"></i> Use Webcam
                  </button>
                </div>
              </div>
            </div>

            <!-- Camera Mode: Live Preview -->
            <div id="vsCameraMode" style="display:none;">
              <div class="vs-camera-container">
                <video id="vsCameraVideo" autoplay playsinline class="vs-camera-video"></video>
                <canvas id="vsCameraCanvas" style="display:none;"></canvas>
                <div class="vs-camera-overlay">
                  <div class="vs-camera-crosshair"></div>
                </div>
              </div>
              <div class="row" style="margin-top:12px;">
                <div class="col-xs-6">
                  <button class="btn btn-success btn-block" id="vsCaptureBtn">
                    <i class="fa fa-camera"></i> Capture
                  </button>
                </div>
                <div class="col-xs-6">
                  <button class="btn btn-default btn-block" id="vsCancelCameraBtn">
                    <i class="fa fa-times"></i> Cancel
                  </button>
                </div>
              </div>
            </div>

          </div>

          <!-- Feature Highlights -->
          <div class="row" style="margin-top:16px;">
            <div class="col-sm-4 col-xs-12" style="margin-bottom:10px;">
              <div class="vs-upload-box" style="padding:16px; text-align:center;">
                <i class="fa fa-bolt" style="font-size:24px; color:var(--vs-primary); margin-bottom:6px;"></i>
                <div style="font-size:12px; font-weight:700; color:#1E293B; font-family:'Inter',sans-serif;">AI Powered</div>
                <div style="font-size:11px; color:#94A3B8;">Visual search engine</div>
              </div>
            </div>
            <div class="col-sm-4 col-xs-12" style="margin-bottom:10px;">
              <div class="vs-upload-box" style="padding:16px; text-align:center;">
                <i class="fa fa-clock-o" style="font-size:24px; color:var(--vs-success); margin-bottom:6px;"></i>
                <div style="font-size:12px; font-weight:700; color:#1E293B; font-family:'Inter',sans-serif;">Instant Results</div>
                <div style="font-size:11px; color:#94A3B8;">Under 5 seconds</div>
              </div>
            </div>
            <div class="col-sm-4 col-xs-12" style="margin-bottom:10px;">
              <div class="vs-upload-box" style="padding:16px; text-align:center;">
                <i class="fa fa-bar-chart" style="font-size:24px; color:var(--vs-accent); margin-bottom:6px;"></i>
                <div style="font-size:12px; font-weight:700; color:#1E293B; font-family:'Inter',sans-serif;">Smart Insights</div>
                <div style="font-size:11px; color:#94A3B8;">AI-driven analytics</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Recent Searches (row-wise sidebar) -->
        <div class="col-md-4 col-sm-5 col-xs-12">
          <div id="vsHistoryPanel" class="vs-history-panel vs-history-sidebar" style="display:none;">
            <div class="vs-history-title"><i class="fa fa-history"></i> Recent Searches</div>
            <div class="vs-history-list vs-history-rows" id="vsHistoryList"></div>
          </div>
        </div>
      </div>
    </div>



    <!-- ═══════════════════════════════════════
         Results Section (dark spotlight + insights sidebar)
         ═══════════════════════════════════════ -->
    <div id="vsResultsSection" style="display:none;">
      <div class="row vs-results-row">
        <!-- Left Column: Dark Spotlight Results (75%) -->
        <div class="col-md-9 col-sm-8 col-xs-12">
          <div class="vs-spotlight" id="vsSpotlight">

            <!-- Spotlight Header: On Sale tag -->
            <div class="vs-spotlight-header">
              <span class="label label-success" id="vsOnSaleTag" style="display:none;">
                <i class="fa fa-shopping-bag"></i> On Sale
              </span>
            </div>

            <!-- Top Matches Container (dark-styled cards) -->
            <div id="vsTopMatchesContainer"></div>

            <!-- Show More Button (appears when > 2 matches) -->
            <button class="btn vs-show-more-btn" id="vsShowMoreBtn" style="display:none;">
              <i class="fa fa-chevron-down"></i> Show More Matches
              <span class="badge" id="vsRemainingCount" style="background:rgba(255,255,255,0.2); margin-left:8px;">0</span>
            </button>

          </div>
        </div><!-- /.col-md-9 -->

        <!-- Right Column: Search Insights Sidebar (25%) -->
        <div class="col-md-3 col-sm-4 col-xs-12">
          <div id="vsInsightsPanel" class="vs-insights-panel">
            <div class="vs-insights-header">
              <span class="vs-insights-title">
                <i class="fa fa-line-chart"></i> Search Insights
              </span>
              <span class="label vs-insights-badge">AI Analytics</span>
            </div>
            <div class="vs-insights-subtitle">Analytics for matched product category</div>
            <div id="vsInsightRows" class="vs-insight-rows">
              <!-- 5 insight rows rendered by JS after search completes -->
              <div id="vsInsightRowsLoader" class="vs-insights-loader">
                <div class="vs-insight-skeleton"></div>
                <div class="vs-insight-skeleton"></div>
                <div class="vs-insight-skeleton"></div>
                <div class="vs-insight-skeleton"></div>
                <div class="vs-insight-skeleton"></div>
              </div>
            </div>
          </div>
        </div><!-- /.col-md-3 -->

      </div><!-- /.row -->
    </div>

    <!-- No Results -->
    <div id="vsNoResults" style="display:none;">
      <div class="row">
        <div class="col-md-6 col-md-offset-3 text-center" style="padding:60px 20px;">
          <div class="vs-no-results-icon" aria-hidden="true">
            <i class="fa fa-search"></i>
          </div>
          <h4 style="margin-top:16px; color:#1E293B; font-family: 'Inter', sans-serif; font-weight: 700;">No matching products found</h4>
          <p class="text-muted">Try a different image or angle for better results</p>
          <button class="btn btn-primary btn-lg vs-new-search-btn" id="vsRetryBtn" aria-label="Try a new search"><i class="fa fa-refresh"></i> Try Again</button>
        </div>
      </div>
    </div>


    <!-- Image + Details Modal -->
    <div class="modal fade" id="vsDetailModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content vs-modal-content">
          <div class="modal-header vs-modal-header">
            <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
            <h4 class="modal-title" id="vsModalTitle"><i class="fa fa-tag"></i> Product Details</h4>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-5 text-center">
                <div class="vs-modal-img-wrap">
                  <img id="vsModalImg" class="vs-modal-img" src="" alt="Product">
                  <div class="vs-modal-zoom-hint"><i class="fa fa-search-plus"></i> Click to zoom</div>
                </div>
                <div class="vs-modal-score-wrap">
                  <span class="label vs-modal-score-badge" id="vsModalScore">-</span>
                </div>
              </div>
              <div class="col-md-7">
                <div class="vs-modal-details">
                  <div class="vs-modal-tag-header">
                    <span class="vs-modal-tag-code" id="vsModalTagCode">-</span>
                    <span class="vs-modal-status-badge" id="vsModalStatus">-</span>
                  </div>
                  <div class="vs-modal-info-grid">
                    <!-- Stock Code removed: not applicable in Source Version -->
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-folder-o"></i> Category</span>
                      <span class="vs-info-value" id="vsModalCategory">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-cube"></i> Product</span>
                      <span class="vs-info-value" id="vsModalProduct">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-paint-brush"></i> Design</span>
                      <span class="vs-info-value" id="vsModalDesign">-</span>
                    </div>
                    <!-- SOURCE VERSION: Sub-Design instead of Collection -->
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-bookmark-o"></i> Sub-Design</span>
                      <span class="vs-info-value" id="vsModalSubDesign">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-cube"></i> Gross Wt</span>
                      <span class="vs-info-value" id="vsModalGrossWt">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-cubes"></i> Net Wt</span>
                      <span class="vs-info-value" id="vsModalNetWt">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-th-large"></i> Piece</span>
                      <span class="vs-info-value" id="vsModalPiece">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-diamond"></i> Purity</span>
                      <span class="vs-info-value" id="vsModalPurity">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-building"></i> Branch</span>
                      <span class="vs-info-value" id="vsModalBranch">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-user"></i> Karigar Code</span>
                      <span class="vs-info-value" id="vsModalKarigar">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-minus-circle"></i> Less Wt</span>
                      <span class="vs-info-value" id="vsModalLessWt">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-calendar"></i> <span id="vsModalStockAgeLabel">Stock Age</span></span>
                      <span class="vs-info-value" id="vsModalStockAge">-</span>
                    </div>
                    <div class="vs-info-item">
                      <span class="vs-info-label"><i class="fa fa-arrows-alt"></i> Size</span>
                      <span class="vs-info-value" id="vsModalSize">-</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Insight Detail Modal (AdminLTE style — content injected by JS) -->
    <div class="modal fade" id="vsInsightDetailModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header" id="vsInsightModalHeader" style="border-bottom:3px solid #4F46E5;">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="vsInsightModalTitle" style="font-family:'Inter',sans-serif; font-weight:700;">
              <i class="fa fa-line-chart"></i> Insight Details
            </h4>
          </div>
          <div class="modal-body" id="vsInsightModalBody" style="max-height:70vh; overflow-y:auto;">
            <!-- Dynamically populated per insight type -->
          </div>
        </div>
      </div>
    </div>

    <!-- Image Zoom Lightbox -->
    <div id="vsImageLightbox" class="vs-lightbox" style="display:none;">
      <div class="vs-lightbox-backdrop"></div>
      <div class="vs-lightbox-content">
        <img id="vsLightboxImg" src="" alt="Zoomed">
        <button class="vs-lightbox-close" id="vsLightboxClose"><i class="fa fa-times"></i></button>
      </div>
    </div>

  </section>
</div>

<script defer src="<?php echo base_url(); ?>assets/js/visual_search.js"></script>
