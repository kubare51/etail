/**
 * Suggestive Boards — WinPixel Analytics Dashboard for Source Version (retail_v5)
 *
 * Boards: Fast Movers, Branch Rotation, Purchase Intel, FNS Classification,
 * Stock Availability, Design Performance, Age Analysis, AI Suggestions,
 * Cross-Branch Intel, Sales Patterns.
 *
 * Enhanced filters: Branch, Category, Product, Collection (Sub-Design),
 * Karigar, Weight Range, Age Days.
 *
 * Filter dropdowns populated from local MySQL (get_filter_options endpoint).
 * Analytics data fetched via proxy → WinPixel API (server-side, API key hidden).
 */
"use strict";

// Chart.js v4 is saved as window.Chart4 in boards.php before AdminLTE's
// footer loads Chart.js v1 which would overwrite window.Chart.
var ChartJS = window.Chart4 || window.Chart;

$(document).ready(function () {

  // ═══════════════════════════════════════════
  // Config
  // ═══════════════════════════════════════════
  // Detect base URL — works on any deployment (local /AMS/, staging /, production /)
  var _base = (function () {
    if ($("base").length) return $("base").attr("href");
    var path = window.location.pathname;
    var idx = path.indexOf("index.php");
    if (idx > -1) return window.location.origin + path.substring(0, idx);
    var parts = path.replace(/\/+$/, "").split("/");
    parts.pop();
    return window.location.origin + parts.join("/") + "/";
  })();

  var proxyUrl = _base + "index.php/admin_visual_search/proxy_analytics";
  var filterOptionsUrl = _base + "index.php/admin_visual_search/get_filter_options";

  // ═══════════════════════════════════════════
  // Read product context from URL params (passed from search results)
  // ═══════════════════════════════════════════
  var urlParams = new URLSearchParams(window.location.search);
  var productContext = {
    tag_code:    urlParams.get("tag_code") || "",
    category:    urlParams.get("category") || "",
    collection:  urlParams.get("collection") || "",
    branch:      urlParams.get("branch") || "",
    product:     urlParams.get("product") || "",
    design:      urlParams.get("design") || "",
    karigar:     urlParams.get("karigar") || "",
    weight_from: urlParams.get("weight_from") || "",
    weight_to:   urlParams.get("weight_to") || ""
  };
  var hasContext = productContext.tag_code || productContext.category || productContext.collection || productContext.branch || productContext.product || productContext.karigar || productContext.weight_from;

  // Dual-mode: context mode (from search) vs direct access mode (filters)
  if (hasContext) {
    // Context mode — show banner
    $(".content").addClass("vsb-context-mode");
    var catLabel = productContext.category || productContext.tag_code || "Unknown";
    var collLabel = productContext.collection ? " / " + productContext.collection : "";
    $("#vsbContextCategory").text(catLabel);
    $("#vsbContextCollection").text(collLabel);
    $("#vsbContextBanner").show();
  } else {
    // Direct access mode — hide banner, populate filter modal dropdowns
    $("#vsbContextBanner").hide();
    loadFilterOptions();
  }

  var currentBoard = "ai-suggestions";
  var loadedBoards = {};

  // ═══════════════════════════════════════════
  // Tab Navigation
  // ═══════════════════════════════════════════
  $(".vsb-utab").on("click", function () {
    var board = $(this).data("board");
    if (board === currentBoard) return;

    $(".vsb-utab").removeClass("active");
    $(this).addClass("active");

    $(".vsb-board").hide();
    // Explicitly hide comparison panel + footer when leaving AI tab
    $("#vsbComparisonPanel").hide();
    $("#vsbFooterBar").hide();
    $("#vsb-" + board).show();
    // Re-show comparison panel only when returning to AI suggestions
    if (board === "ai-suggestions") {
      $("#vsbComparisonPanel").show();
      $("#vsbFooterBar").show();
    }

    currentBoard = board;

    // Load if not already loaded
    if (!loadedBoards[board]) {
      loadBoard(board);
    }
  });

  // Refresh button
  $("#vsbRefreshBtn").on("click", function () {
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
  });

  // Days filter change
  $("#vsbDaysFilter").on("change", function () {
    var val = $(this).val();
    if (val === "custom") {
      $("#vsbCustomRange").show();
      return; // Don't reload until user clicks Go
    }
    $("#vsbCustomRange").hide();
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
  });

  // Custom range Go button
  $("#vsbCustomGoBtn").on("click", function () {
    var from = parseInt($("#vsbCustomFrom").val()) || 0;
    var to   = parseInt($("#vsbCustomTo").val()) || 365;
    if (from >= to) {
      alert("'From' must be less than 'To'");
      return;
    }
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
  });

  // Age type filter change
  $("#vsbAgeTypeFilter").on("change", function () {
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
  });

  // ═══════════════════════════════════════════
  // Slide-Out Filter Panel
  // ═══════════════════════════════════════════

  var _filterData = { branches: [], categories: [], products: [], designs: [], sub_designs: [], karigars: [] };

  // Open/close slider
  $("#vsbFilterModalBtn").on("click", function () {
    $("#vsbFilterOverlay").addClass("active");
    $("#vsbFilterSlider").addClass("open");
  });
  function closeSlider() {
    $("#vsbFilterOverlay").removeClass("active");
    $("#vsbFilterSlider").removeClass("open");
  }
  $("#vsbSliderClose").on("click", closeSlider);
  $("#vsbFilterOverlay").on("click", closeSlider);

  // Tab switching for filter panel
  $(document).on("click", ".vsb-filter-tab-label", function () {
    if ($(this).hasClass("vsb-tab-locked")) return; // locked tabs can't be clicked
    var tab = $(this).data("filter-tab");
    // Activate tab label
    $(".vsb-filter-tab-label").removeClass("active");
    $(this).addClass("active");
    // Show corresponding panel
    $(".vsb-filter-tab-panel").removeClass("active");
    $(".vsb-filter-tab-panel[data-panel='" + tab + "']").addClass("active");
  });

  // ── Checkbox toggle: "All" vs specific items ──
  // Clicking "All" unchecks all specific items
  $(document).on("change", ".vsb-check-all", function () {
    var name = $(this).attr("name");
    if ($(this).is(":checked")) {
      $("input[name='" + name + "']").not(".vsb-check-all").prop("checked", false);
    } else {
      // Don't allow unchecking "All" without selecting something
      $(this).prop("checked", true);
    }
    updateSliderBadges();
  });

  // Clicking any specific item unchecks "All"
  $(document).on("change", "input[type='checkbox']:not(.vsb-check-all)", function () {
    var name = $(this).attr("name");
    if (!name) return;
    var anyChecked = false;
    $("input[name='" + name + "']:checked").each(function () {
      if ($(this).val()) anyChecked = true;
    });
    if (anyChecked) {
      $("input[name='" + name + "'].vsb-check-all").prop("checked", false);
    } else {
      // Nothing selected → re-check "All"
      $("input[name='" + name + "'].vsb-check-all").prop("checked", true);
    }
    updateSliderBadges();
  });

  // Helper: get selected checkbox values (excluding "All" which has value="")
  function getCheckedValues(name) {
    var vals = [];
    $("input[name='" + name + "']:checked").each(function () {
      if ($(this).val()) vals.push($(this).val());
    });
    return vals.join(",");
  }

  // Apply filters
  $("#vsbFilterApplyBtn").on("click", function () {
    productContext.branch      = getCheckedValues("branch");
    productContext.category    = getCheckedValues("category");
    productContext.product     = getCheckedValues("product");
    productContext.design      = getCheckedValues("design");
    productContext.collection  = getCheckedValues("subdesign");
    productContext.karigar     = getCheckedValues("karigar");
    productContext.weight_from = $("#vsbWeightFrom").val() || "";
    productContext.weight_to   = $("#vsbWeightTo").val() || "";
    hasContext = !!(productContext.category || productContext.collection || productContext.branch ||
                productContext.product || productContext.karigar || productContext.weight_from || productContext.design);
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
    updateFilterChips();
    closeSlider();
  });

  // Clear all
  $("#vsbFilterClearBtn").on("click", function () {
    $("input[name='branch'], input[name='category'], input[name='product'], input[name='design'], input[name='subdesign'], input[name='karigar']").prop("checked", false);
    $(".vsb-check-all").prop("checked", true);
    $("#vsbWeightFrom").val("");
    $("#vsbWeightTo").val("");
    $(".vsb-slider-search").val("");
    productContext.category = ""; productContext.collection = ""; productContext.branch = "";
    productContext.product = ""; productContext.design = ""; productContext.karigar = "";
    productContext.weight_from = ""; productContext.weight_to = "";
    hasContext = false;
    renderProductList(); renderDesignList(); initCascadeLocks(); updateSliderBadges();
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
    updateFilterChips();
  });

  // Filter chips
  function updateFilterChips() {
    var $chips = $("#vsbActiveFilters"), $count = $("#vsbFilterCount");
    var html = "", count = 0;
    var chipMap = [
      { key: "branch",     icon: "fa-building",     label: productContext.branch },
      { key: "category",   icon: "fa-th-large",     label: productContext.category },
      { key: "product",    icon: "fa-cube",          label: productContext.product },
      { key: "design",     icon: "fa-diamond",       label: productContext.design },
      { key: "collection", icon: "fa-bookmark-o",    label: productContext.collection },
      { key: "karigar",    icon: "fa-user",          label: productContext.karigar },
      { key: "weight",     icon: "fa-balance-scale", label: (productContext.weight_from || productContext.weight_to) ? (productContext.weight_from || "0") + "g–" + (productContext.weight_to || "∞") + "g" : "" }
    ];
    chipMap.forEach(function (c) {
      if (c.label) {
        html += '<span class="vsb-filter-chip"><i class="fa ' + c.icon + '"></i> ' + esc(c.label) + ' <i class="fa fa-times" data-filter="' + c.key + '"></i></span>';
        count++;
      }
    });
    if (count > 0) { $chips.html(html).show(); $count.text(count).show(); }
    else { $chips.hide(); $count.hide(); }
  }

  // Remove individual chip
  $(document).on("click", ".vsb-filter-chip .fa-times", function () {
    var f = $(this).data("filter");
    // Helper to reset a checkbox group to "All"
    function resetCheckGroup(name) {
      $("input[name='" + name + "']").not(".vsb-check-all").prop("checked", false);
      $("input[name='" + name + "'].vsb-check-all").prop("checked", true);
    }
    var m = {
      category:   function () { productContext.category = ""; resetCheckGroup("category"); productContext.product = ""; resetCheckGroup("product"); productContext.design = ""; resetCheckGroup("design"); productContext.collection = ""; resetCheckGroup("subdesign"); renderProductList(); renderDesignList(); setTabLocked("product", true); setTabLocked("design", true); setTabLocked("subdesign", true); },
      product:    function () { productContext.product = ""; resetCheckGroup("product"); productContext.design = ""; resetCheckGroup("design"); productContext.collection = ""; resetCheckGroup("subdesign"); renderDesignList(); setTabLocked("design", true); setTabLocked("subdesign", true); },
      design:     function () { productContext.design = ""; resetCheckGroup("design"); productContext.collection = ""; resetCheckGroup("subdesign"); setTabLocked("subdesign", true); },
      collection: function () { productContext.collection = ""; resetCheckGroup("subdesign"); },
      branch:     function () { productContext.branch = ""; resetCheckGroup("branch"); },
      karigar:    function () { productContext.karigar = ""; resetCheckGroup("karigar"); },
      weight:     function () { productContext.weight_from = ""; productContext.weight_to = ""; $("#vsbWeightFrom").val(""); $("#vsbWeightTo").val(""); }
    };
    if (m[f]) m[f]();
    hasContext = !!(productContext.category || productContext.collection || productContext.branch ||
                productContext.product || productContext.karigar || productContext.weight_from || productContext.design);
    updateSliderBadges();
    loadedBoards = {};
    _aiLoaded = false;
    loadBoard(currentBoard);
    updateFilterChips();
  });

  // ── Radio list renderer ──
  function renderRadioList($container, items, radioName, valFn, lblFn) {
    var html = "";
    items.forEach(function (item) {
      html += '<label class="vsb-slider-item"><input type="checkbox" name="' + radioName + '" value="' + esc(valFn(item)) + '"> <span>' + esc(lblFn(item)) + '</span></label>';
    });
    $container.html(html);
  }

  function renderBranchList() {
    renderRadioList($("#vsbBranchList"), _filterData.branches, "branch", function (b) { return b.name; }, function (b) { return b.name; });
  }
  function renderCategoryList() {
    renderRadioList($("#vsbCategoryList"), _filterData.categories, "category", function (c) { return c.name; }, function (c) { return c.name; });
  }
  function renderProductList(catIds) {
    var items = _filterData.products;
    if (catIds) {
      if (!Array.isArray(catIds)) catIds = [catIds];
      if (catIds.length > 0) items = items.filter(function (p) { return catIds.indexOf(String(p.cat_id)) > -1 || catIds.indexOf(Number(p.cat_id)) > -1; });
    }
    renderRadioList($("#vsbProductList"), items, "product", function (p) { return p.product_name; }, function (p) { return p.product_name; });
  }
  function renderDesignList(productIds) {
    var items = _filterData.designs;
    if (productIds) {
      if (!Array.isArray(productIds)) productIds = [productIds];
      if (productIds.length > 0) items = items.filter(function (d) { return productIds.indexOf(String(d.product_id)) > -1 || productIds.indexOf(Number(d.product_id)) > -1; });
    }
    renderRadioList($("#vsbDesignList"), items, "design", function (d) { return d.design_name; }, function (d) { return d.design_name; });
  }
  function renderSubDesignList() {
    renderRadioList($("#vsbSubDesignList"), _filterData.sub_designs, "subdesign", function (s) { return s.sub_design_name; }, function (s) { return s.sub_design_name; });
  }
  function renderKarigarList() {
    renderRadioList($("#vsbKarigarList"), _filterData.karigars, "karigar", function (k) { return k.name_karigar; }, function (k) { return k.name_karigar + (k.code_karigar ? " [" + k.code_karigar + "]" : ""); });
  }

  // ── Cascade: Category → Product → Design → Sub-Design ──
  // Lock/unlock tab helper
  function setTabLocked(tabName, locked) {
    var $tab = $(".vsb-filter-tab-label[data-filter-tab='" + tabName + "']");
    var $panel = $(".vsb-filter-tab-panel[data-panel='" + tabName + "']");
    if (locked) {
      $tab.addClass("vsb-tab-locked");
      $panel.find(".vsb-slider-list").html('<div class="vsb-cascade-hint"><i class="fa fa-lock"></i> Select a parent filter first</div>');
      $panel.find(".vsb-slider-search").prop("disabled", true).val("");
    } else {
      $tab.removeClass("vsb-tab-locked");
      $panel.find(".vsb-slider-search").prop("disabled", false);
    }
  }

  // Auto-switch to next tab after selection
  function switchToTab(tabName) {
    $(".vsb-filter-tab-label").removeClass("active");
    $(".vsb-filter-tab-label[data-filter-tab='" + tabName + "']").addClass("active");
    $(".vsb-filter-tab-panel").removeClass("active");
    $(".vsb-filter-tab-panel[data-panel='" + tabName + "']").addClass("active");
  }

  // Initialize locked state on load
  function initCascadeLocks() {
    setTabLocked("product", true);
    setTabLocked("design", true);
    setTabLocked("subdesign", true);
  }

  // Helper: get selected category IDs (array)
  function getSelectedCatIds() {
    var ids = [];
    $("input[name='category']:checked").each(function () {
      var name = $(this).val();
      if (!name) return;
      var c = _filterData.categories.find(function (x) { return x.name === name; });
      if (c) ids.push(c.id_ret_category);
    });
    return ids;
  }

  // Helper: get selected product IDs (array)
  function getSelectedProductIds() {
    var ids = [];
    $("input[name='product']:checked").each(function () {
      var name = $(this).val();
      if (!name) return;
      var p = _filterData.products.find(function (x) { return x.product_name === name; });
      if (p) ids.push(p.pro_id);
    });
    return ids;
  }

  // Category change → unlock Product, reset Product/Design/SubDesign
  $(document).on("change", "input[name='category']", function () {
    var catIds = getSelectedCatIds();
    // Reset children selections
    $("input[name='product']").not(".vsb-check-all").prop("checked", false);
    $("input[name='product'].vsb-check-all").prop("checked", true);
    $("input[name='design']").not(".vsb-check-all").prop("checked", false);
    $("input[name='design'].vsb-check-all").prop("checked", true);
    $("input[name='subdesign']").not(".vsb-check-all").prop("checked", false);
    $("input[name='subdesign'].vsb-check-all").prop("checked", true);
    // Lock/unlock
    if (catIds.length > 0) {
      setTabLocked("product", false);
      renderProductList(catIds);
    } else {
      setTabLocked("product", true);
    }
    setTabLocked("design", true);
    setTabLocked("subdesign", true);
    renderDesignList();
    updateSliderBadges();
  });

  // Product change → unlock Design, reset Design/SubDesign
  $(document).on("change", "input[name='product']", function () {
    var prodIds = getSelectedProductIds();
    // Reset children selections
    $("input[name='design']").not(".vsb-check-all").prop("checked", false);
    $("input[name='design'].vsb-check-all").prop("checked", true);
    $("input[name='subdesign']").not(".vsb-check-all").prop("checked", false);
    $("input[name='subdesign'].vsb-check-all").prop("checked", true);
    // Lock/unlock
    if (prodIds.length > 0) {
      setTabLocked("design", false);
      renderDesignList(prodIds);
    } else {
      setTabLocked("design", true);
    }
    setTabLocked("subdesign", true);
    updateSliderBadges();
  });

  // Design change → unlock Sub-Design
  $(document).on("change", "input[name='design']", function () {
    // Reset sub-design
    $("input[name='subdesign']").not(".vsb-check-all").prop("checked", false);
    $("input[name='subdesign'].vsb-check-all").prop("checked", true);
    var anyDesign = false;
    $("input[name='design']:checked").each(function () { if ($(this).val()) anyDesign = true; });
    if (anyDesign) {
      setTabLocked("subdesign", false);
      renderSubDesignList();
    } else {
      setTabLocked("subdesign", true);
    }
    updateSliderBadges();
  });

  // Branch, Karigar: independent — just update badges
  $(document).on("change", "input[name='branch'], input[name='subdesign'], input[name='karigar']", function () {
    updateSliderBadges();
  });

  // ── Search-as-you-type ──
  function bindSearch(searchId, listId) {
    $(document).on("input", "#" + searchId, function () {
      var q = $(this).val().toLowerCase();
      $("#" + listId + " .vsb-slider-item").each(function () {
        $(this).toggle($(this).find("span").text().toLowerCase().indexOf(q) > -1);
      });
    });
  }
  bindSearch("vsbBranchSearch", "vsbBranchList");
  bindSearch("vsbCategorySearch", "vsbCategoryList");
  bindSearch("vsbProductSearch", "vsbProductList");
  bindSearch("vsbDesignSearch", "vsbDesignList");
  bindSearch("vsbSubDesignSearch", "vsbSubDesignList");
  bindSearch("vsbKarigarSearch", "vsbKarigarList");

  // ── Badge counts ──
  function updateSliderBadges() {
    var idMap = { branch: "vsbBranchBadge", category: "vsbCategoryBadge", product: "vsbProductBadge", design: "vsbDesignBadge", subdesign: "vsbSubDesignBadge", karigar: "vsbKarigarBadge" };
    ["branch", "category", "product", "design", "subdesign", "karigar"].forEach(function (n) {
      var count = 0;
      $("input[name='" + n + "']:checked").each(function () { if ($(this).val()) count++; });
      var $b = $("#" + idMap[n]);
      if (count > 0) { $b.text(count).addClass("active"); }
      else { $b.text("").removeClass("active"); }
    });
  }

  // ── Load filter data from local MySQL ──
  function loadFilterOptions() {
    $.ajax({
      url: filterOptionsUrl,
      type: "POST",
      dataType: "json",
      success: function (resp) {
        if (!resp || !resp.status || !resp.data) return;
        _filterData = resp.data;
        renderBranchList();
        renderCategoryList();
        renderProductList();
        renderDesignList();
        renderSubDesignList();
        renderKarigarList();
        initCascadeLocks();
      },
      error: function () {
        console.error("Failed to load filter options from local DB");
      }
    });
  }

  // ═══════════════════════════════════════════
  // Proxy Fetch
  // ═══════════════════════════════════════════
  function fetchAnalytics(endpoint, params, callback) {
    // Merge all active filters into API params
    var mergedParams = $.extend({}, params);
    if (productContext.category)    mergedParams.category = productContext.category;
    if (productContext.collection)  mergedParams.collection_name = productContext.collection;
    if (productContext.branch)      mergedParams.branch_name = productContext.branch;
    if (productContext.tag_code)    mergedParams.tag_code = productContext.tag_code;
    if (productContext.product)     mergedParams.product_name = productContext.product;
    if (productContext.design)      mergedParams.design_name = productContext.design;
    if (productContext.karigar)     mergedParams.karigar_name = productContext.karigar;
    if (productContext.weight_from) mergedParams.weight_from = productContext.weight_from;
    if (productContext.weight_to)   mergedParams.weight_to = productContext.weight_to;

    $.ajax({
      url: proxyUrl,
      type: "POST",
      data: {
        endpoint: endpoint,
        params: JSON.stringify(mergedParams)
      },
      dataType: "json",
      success: function (data) {
        callback(null, data);
      },
      error: function (xhr, status) {
        callback("Network error: " + status, null);
      }
    });
  }

  function getDays() {
    var val = $("#vsbDaysFilter").val();
    if (val === "custom") {
      return parseInt($("#vsbCustomTo").val()) || 365;
    }
    return parseInt(val) || 90;
  }

  // Get custom age range: { from: N, to: N } or null for presets
  function getAgeRange() {
    var val = $("#vsbDaysFilter").val();
    if (val === "custom") {
      return {
        from: parseInt($("#vsbCustomFrom").val()) || 0,
        to:   parseInt($("#vsbCustomTo").val()) || 365
      };
    }
    return null;
  }

  // Get age type: 1 = actual age (tag creation), 2 = branch age (inward)
  function getAgeType() {
    return parseInt($("#vsbAgeTypeFilter").val()) || 1;
  }

  // ═══════════════════════════════════════════
  // Board Loader
  // ═══════════════════════════════════════════
  function loadBoard(board) {
    var days = getDays();

    switch (board) {
      case "fast-movers":
        loadFastMovers(days);
        break;
      case "branch-rotation":
        loadBranchRotation(days);
        break;
      case "purchase-intel":
        loadPurchaseIntel(days);
        break;
      case "fns-classification":
        loadFNSClassification(days);
        break;
      case "stock-availability":
        loadStockAvailability();
        break;
      case "design-performance":
        loadDesignPerformance(days);
        break;
      case "age-analysis":
        loadAgeAnalysis(days);
        break;
      case "ai-suggestions":
        loadAISuggestions(days);
        break;
      case "cross-branch-intel":
        loadCrossBranchIntel(days);
        break;
      case "sales-patterns":
        loadSalesPatterns(days);
        break;
    }
  }

  // ═══════════════════════════════════════════
  // Fast Movers Board
  // ═══════════════════════════════════════════
  function loadFastMovers(days) {
    showLoading("FastMovers");

    fetchAnalytics("collection-velocity", { days: days }, function (err, data) {
      hideLoading("FastMovers");

      if (err || !data || !data.collections) {
        showError("FastMovers", err || "No data returned");
        return;
      }

      var collections = data.collections || [];
      loadedBoards["fast-movers"] = true;

      var html = '<div class="row">';

      // KPI Cards
      var totalSold = 0, totalStock = 0, totalRevenue = 0;
      collections.forEach(function (c) {
        totalSold += c.sold_count || 0;
        totalStock += c.stock_count || 0;
        totalRevenue += c.revenue || 0;
      });

      html += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-success">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-shopping-cart"></i></div>' +
          '<div class="vsb-kpi-value">' + totalSold + '</div>' +
          '<div class="vsb-kpi-label">Items Sold (' + days + 'd)</div>' +
        '</div></div>';

      html += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-warning">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-cubes"></i></div>' +
          '<div class="vsb-kpi-value">' + totalStock + '</div>' +
          '<div class="vsb-kpi-label">Current Stock</div>' +
        '</div></div>';

      html += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-primary">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-inr"></i></div>' +
          '<div class="vsb-kpi-value">' + formatCurrency(totalRevenue) + '</div>' +
          '<div class="vsb-kpi-label">Total Revenue</div>' +
        '</div></div>';

      html += '</div>';

      // Charts Row — Doughnut (revenue split) + Bar (sold vs stock)
      html += '<div class="row">' +
        '<div class="col-md-5">' +
          '<div class="vsb-chart-card">' +
            '<div class="vsb-chart-title"><i class="fa fa-pie-chart"></i> Revenue by Collection</div>' +
            '<div class="vsb-chart-wrap"><canvas id="vsbFmDoughnut"></canvas></div>' +
          '</div>' +
        '</div>' +
        '<div class="col-md-7">' +
          '<div class="vsb-chart-card">' +
            '<div class="vsb-chart-title"><i class="fa fa-bar-chart"></i> Sold vs Current Stock</div>' +
            '<div class="vsb-chart-wrap"><canvas id="vsbFmBar"></canvas></div>' +
          '</div>' +
        '</div>' +
      '</div>';

      // Collection Table
      html += '<div class="vsb-table-card">' +
        '<div class="vsb-table-title"><i class="fa fa-rocket"></i> Collection Velocity</div>' +
        '<div class="table-responsive">' +
        '<table class="table table-hover vsb-table">' +
        '<thead><tr>' +
          '<th>#</th><th>Collection</th><th>Sold</th><th>Stock</th>' +
          '<th>Revenue</th><th>Turnover</th><th>Avg Days to Sell</th><th>Status</th>' +
        '</tr></thead><tbody>';

      collections.forEach(function (c, i) {
        var turnover = parseFloat(c.turnover_rate) || 0;
        var statusClass = turnover >= 1.0 ? "vsb-badge-success" :
                          turnover >= 0.3 ? "vsb-badge-warning" : "vsb-badge-danger";
        var statusText = turnover >= 1.0 ? "Fast" :
                         turnover >= 0.3 ? "Moderate" : "Slow";

        html += '<tr>' +
          '<td>' + (i + 1) + '</td>' +
          '<td><strong>' + esc(c.name) + '</strong></td>' +
          '<td>' + (c.sold_count || 0) + '</td>' +
          '<td>' + (c.stock_count || 0) + '</td>' +
          '<td>' + formatCurrency(c.revenue || 0) + '</td>' +
          '<td><strong>' + turnover.toFixed(2) + 'x</strong></td>' +
          '<td>' + (c.days_to_sell || '-') + '</td>' +
          '<td><span class="vsb-badge ' + statusClass + '">' + statusText + '</span></td>' +
        '</tr>';
      });

      html += '</tbody></table></div></div>';

      $("#vsbFastMoversContent").html(html).show();

      // Render Chart.js charts after DOM insert
      renderFastMoverCharts(collections);

      // Fetch top selling items with images
      fetchAnalytics("top-selling-items", { days: days, limit: 12 }, function (imgErr, imgData) {
        if (imgErr || !imgData || !imgData.items) return;
        var items = imgData.items;
        if (items.length === 0) return;

        var gallery = '<div class="vsb-table-card" style="margin-top:16px;">' +
          '<div class="vsb-table-title"><i class="fa fa-image"></i> Top Selling Items — Gallery (' + items.length + ')</div>' +
          '<div class="vsb-gallery-grid">';

        items.forEach(function (item) {
          var imgSrc = item.image_url || '';
          var hasImg = imgSrc && imgSrc.length > 5;
          gallery += '<div class="vsb-gallery-item">';
          if (hasImg) {
            gallery += '<div class="vsb-gallery-img"><img src="' + esc(imgSrc) + '" alt="' + esc(item.tag_code) + '" onerror="this.parentNode.innerHTML=\'<i class=\\\'fa fa-image\\\'></i>\'"></div>';
          } else {
            gallery += '<div class="vsb-gallery-img vsb-gallery-noimg"><i class="fa fa-image"></i></div>';
          }
          gallery += '<div class="vsb-gallery-info">' +
            '<div class="vsb-gallery-tag">' + esc(item.tag_code) + '</div>' +
            '<div class="vsb-gallery-product">' + esc(item.product_name) + '</div>' +
            '<div class="vsb-gallery-meta">' +
              '<span class="vsb-gallery-val">' + formatCurrency(item.sale_value) + '</span>' +
              '<span class="vsb-gallery-cat">' + esc(item.category) + '</span>' +
            '</div>' +
            '<div class="vsb-gallery-branch"><i class="fa fa-map-marker"></i> ' + esc(item.branch_name) + '</div>' +
          '</div></div>';
        });

        gallery += '</div></div>';
        $("#vsbFastMoversContent").append(gallery);
      });
    });
  }

  // Chart.js color palette (dark-theme friendly)
  var chartPalette = [
    '#7C3AED', '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
    '#EC4899', '#06B6D4', '#8B5CF6', '#F97316', '#14B8A6'
  ];

  // Store chart instances for cleanup on refresh
  var chartInstances = {};

  function destroyChart(key) {
    if (chartInstances[key]) {
      chartInstances[key].destroy();
      chartInstances[key] = null;
    }
  }

  function renderFastMoverCharts(collections) {
    if (typeof ChartJS === "undefined") return;

    var topN = collections.slice(0, 8);
    var labels = topN.map(function (c) { return c.name || "?"; });
    var revenues = topN.map(function (c) { return c.revenue || 0; });
    var soldCounts = topN.map(function (c) { return c.sold_count || 0; });
    var stockCounts = topN.map(function (c) { return c.stock_count || 0; });
    var colors = topN.map(function (_, i) { return chartPalette[i % chartPalette.length]; });

    // Doughnut — Revenue distribution
    var doughnutEl = document.getElementById("vsbFmDoughnut");
    if (doughnutEl) {
      destroyChart("fmDoughnut");
      chartInstances.fmDoughnut = new ChartJS(doughnutEl.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [{
            data: revenues,
            backgroundColor: colors,
            borderColor: "#fff",
            borderWidth: 2,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "65%",
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                color: "#475569",
                font: { family: "'Inter', sans-serif", size: 11, weight: "600" },
                padding: 12,
                usePointStyle: true,
                pointStyleWidth: 10
              }
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return ctx.label + ": " + formatCurrency(ctx.parsed);
                }
              }
            }
          }
        }
      });
    }

    // Bar — Sold vs Stock
    var barEl = document.getElementById("vsbFmBar");
    if (barEl) {
      destroyChart("fmBar");
      chartInstances.fmBar = new ChartJS(barEl.getContext("2d"), {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Sold",
              data: soldCounts,
              backgroundColor: "rgba(16, 185, 129, 0.8)",
              borderColor: "#10B981",
              borderWidth: 1,
              borderRadius: 4,
              barPercentage: 0.7
            },
            {
              label: "In Stock",
              data: stockCounts,
              backgroundColor: "rgba(59, 130, 246, 0.6)",
              borderColor: "#3B82F6",
              borderWidth: 1,
              borderRadius: 4,
              barPercentage: 0.7
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: {
                color: "#475569",
                font: { family: "'Inter', sans-serif", size: 11, weight: "600" },
                usePointStyle: true,
                pointStyleWidth: 10
              }
            }
          },
          scales: {
            x: {
              ticks: { color: "#94A3B8", font: { size: 10 } },
              grid: { color: "#F1F5F9" }
            },
            y: {
              ticks: { color: "#94A3B8", font: { size: 10 } },
              grid: { color: "#F1F5F9" }
            }
          }
        }
      });
    }
  }

  // ═══════════════════════════════════════════
  // Branch Rotation Board
  // ═══════════════════════════════════════════
  function loadBranchRotation(days) {
    showLoading("BranchRotation");

    fetchAnalytics("branch-rotation", { days: days }, function (err, data) {
      hideLoading("BranchRotation");

      if (err || !data || !data.branches) {
        showError("BranchRotation", err || "No data returned");
        return;
      }

      var branches = data.branches || [];
      loadedBoards["branch-rotation"] = true;

      var html = '<div class="vsb-table-card">' +
        '<div class="vsb-table-title"><i class="fa fa-exchange"></i> Branch Leaderboard</div>' +
        '<div class="table-responsive">' +
        '<table class="table table-hover vsb-table">' +
        '<thead><tr>' +
          '<th>Rank</th><th>Branch</th><th>Sold</th><th>Revenue</th>' +
          '<th>Avg Days</th><th>Categories</th>' +
        '</tr></thead><tbody>';

      branches.forEach(function (b, i) {
        var medal = i === 0 ? "🥇" : i === 1 ? "🥈" : i === 2 ? "🥉" : (i + 1);

        var catBadges = "";
        var cats = b.categories || b.top_categories || [];
        if (cats.length > 0) {
          cats.slice(0, 3).forEach(function (cat) {
            catBadges += '<span class="vsb-badge vsb-badge-outline">' +
              esc(cat.name || cat.category || "?") + ' (' + (cat.sold || cat.count || 0) + ')' +
            '</span> ';
          });
        }

        html += '<tr>' +
          '<td class="vsb-rank">' + medal + '</td>' +
          '<td><strong>' + esc(b.name || b.branch_name) + '</strong></td>' +
          '<td>' + (b.sold || b.total_sold || 0) + '</td>' +
          '<td>' + formatCurrency(b.revenue || 0) + '</td>' +
          '<td>' + (b.days_to_sell || b.avg_days_to_sell || '-') + '</td>' +
          '<td>' + catBadges + '</td>' +
        '</tr>';
      });

      html += '</tbody></table></div></div>';

      // Chart — Horizontal bar for branch sold comparison
      html += '<div class="row"><div class="col-md-12">' +
        '<div class="vsb-chart-card">' +
          '<div class="vsb-chart-title"><i class="fa fa-exchange"></i> Branch Performance Comparison</div>' +
          '<div class="vsb-chart-wrap vsb-chart-wrap-lg"><canvas id="vsbBranchBar"></canvas></div>' +
        '</div>' +
      '</div></div>';

      $("#vsbBranchRotationContent").html(html).show();

      // Render horizontal bar chart
      if (typeof ChartJS !== "undefined" && branches.length > 0) {
        var brLabels = branches.map(function (b) { return b.name || b.branch_name || "?"; });
        var brSold = branches.map(function (b) { return b.sold || b.total_sold || 0; });
        var brColors = branches.map(function (_, i) { return chartPalette[i % chartPalette.length]; });

        var brBarEl = document.getElementById("vsbBranchBar");
        if (brBarEl) {
          destroyChart("branchBar");
          chartInstances.branchBar = new ChartJS(brBarEl.getContext("2d"), {
            type: "bar",
            data: {
              labels: brLabels,
              datasets: [{
                label: "Items Sold",
                data: brSold,
                backgroundColor: brColors.map(function (c) { return c + "CC"; }),
                borderColor: brColors,
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6
              }]
            },
            options: {
              indexAxis: "y",
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display: false }
              },
              scales: {
                x: {
                  ticks: { color: "#94A3B8", font: { size: 10 } },
                  grid: { color: "#F1F5F9" }
                },
                y: {
                  ticks: { color: "#475569", font: { size: 11, weight: "600", family: "'Inter', sans-serif" } },
                  grid: { display: false }
                }
              }
            }
          });
        }
      }
    });
  }

  // ═══════════════════════════════════════════
  // Purchase Intel Board
  // ═══════════════════════════════════════════
  function loadPurchaseIntel(days) {
    showLoading("PurchaseIntel");

    fetchAnalytics("purchase-intel", { days: days }, function (err, data) {
      hideLoading("PurchaseIntel");

      if (err || !data) {
        showError("PurchaseIntel", err || "No data returned");
        return;
      }

      loadedBoards["purchase-intel"] = true;

      var buy = data.buy || [];
      var skip = data.skip || [];
      var watch = data.watch || [];

      var html = '<div class="row">';

      // BUY section
      html += '<div class="col-md-6">' +
        '<div class="vsb-table-card vsb-card-buy">' +
          '<div class="vsb-table-title"><i class="fa fa-arrow-up"></i> BUY — Restock These (' + buy.length + ')</div>';

      if (buy.length > 0) {
        html += '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
          '<th>Design</th><th>Category</th><th>Sold</th><th>Revenue</th><th>Signal</th>' +
          '</tr></thead><tbody>';
        buy.forEach(function (item) {
          html += '<tr><td><strong>' + esc(item.design_code) + '</strong></td>' +
            '<td>' + esc(item.category) + '</td>' +
            '<td>' + item.items_sold + '</td>' +
            '<td>' + formatCurrency(item.revenue) + '</td>' +
            '<td><span class="vsb-badge vsb-badge-success">' + esc(item.signal) + '</span></td></tr>';
        });
        html += '</tbody></table></div>';
      } else {
        html += '<p class="vsb-empty">No BUY signals detected</p>';
      }
      html += '</div></div>';

      // SKIP section
      html += '<div class="col-md-6">' +
        '<div class="vsb-table-card vsb-card-skip">' +
          '<div class="vsb-table-title"><i class="fa fa-arrow-down"></i> SKIP — Avoid Restocking (' + skip.length + ')</div>';

      if (skip.length > 0) {
        html += '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
          '<th>Category</th><th>Stock</th><th>Aged Items</th><th>Aged Value</th>' +
          '</tr></thead><tbody>';
        skip.forEach(function (item) {
          html += '<tr><td><strong>' + esc(item.category) + '</strong></td>' +
            '<td>' + item.current_stock + '</td>' +
            '<td>' + item.dead_stock_count + '</td>' +
            '<td class="text-danger">' + formatCurrency(item.dead_stock_value) + '</td></tr>';
        });
        html += '</tbody></table></div>';
      } else {
        html += '<p class="vsb-empty">No SKIP signals detected</p>';
      }
      html += '</div></div>';

      // WATCH section
      if (watch.length > 0) {
        html += '<div class="col-md-12">' +
          '<div class="vsb-table-card vsb-card-watch">' +
            '<div class="vsb-table-title"><i class="fa fa-eye"></i> WATCH — Customer Interest (' + watch.length + ')</div>' +
            '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
              '<th>Tag Code</th><th>Search Count</th><th>Signal</th>' +
            '</tr></thead><tbody>';
        watch.forEach(function (item) {
          html += '<tr><td><strong>' + esc(item.tag_code) + '</strong></td>' +
            '<td>' + item.search_count + '</td>' +
            '<td>' + esc(item.signal) + '</td></tr>';
        });
        html += '</tbody></table></div></div></div>';
      }

      html += '</div>';

      $("#vsbPurchaseIntelContent").html(html).show();
    });
  }

  // ═══════════════════════════════════════════
  // FNS Classification Board (Fast / Non-moving / Slow)
  // ═══════════════════════════════════════════
  function loadFNSClassification(days) {
    showLoading("FNSClassification");

    fetchAnalytics("fns-classification", {
      days: days,
      age_type: getAgeType()
    }, function (err, data) {
      hideLoading("FNSClassification");

      if (err || !data || !data.summary) {
        showError("FNSClassification", err || "No data returned");
        return;
      }

      loadedBoards["fns-classification"] = true;

      var s = data.summary;
      var cats = data.categories || [];
      var html = "";

      // ── FNS Summary KPIs ──
      html += '<div class="row" style="margin-bottom:16px;">';

      // Fast Moving Card
      html += '<div class="col-md-3">' +
        '<div class="vsb-fns-card vsb-fns-fast">' +
          '<div class="vsb-fns-icon"><i class="fa fa-rocket"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Fast Moving</div>' +
            '<div class="vsb-fns-value">' + (s.fast_items || 0) + ' items</div>' +
            '<div class="vsb-fns-sub">' + formatCurrency(s.fast_value || 0) + ' · ' + (s.fast_pct || 0) + '%</div>' +
          '</div>' +
          '<div class="vsb-fns-pct">' + (s.fast_pct || 0) + '%</div>' +
        '</div></div>';

      // Slow Moving Card
      html += '<div class="col-md-3">' +
        '<div class="vsb-fns-card vsb-fns-slow">' +
          '<div class="vsb-fns-icon"><i class="fa fa-hourglass-half"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Slow Moving</div>' +
            '<div class="vsb-fns-value">' + (s.slow_items || 0) + ' items</div>' +
            '<div class="vsb-fns-sub">' + formatCurrency(s.slow_value || 0) + ' · ' + (s.slow_pct || 0) + '%</div>' +
          '</div>' +
          '<div class="vsb-fns-pct">' + (s.slow_pct || 0) + '%</div>' +
        '</div></div>';

      // Non-Moving Card
      html += '<div class="col-md-3">' +
        '<div class="vsb-fns-card vsb-fns-nonmoving">' +
          '<div class="vsb-fns-icon"><i class="fa fa-ban"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Non-Moving</div>' +
            '<div class="vsb-fns-value">' + (s.non_moving_items || 0) + ' items</div>' +
            '<div class="vsb-fns-sub">' + formatCurrency(s.non_moving_value || 0) + ' · ' + (s.non_moving_pct || 0) + '%</div>' +
          '</div>' +
          '<div class="vsb-fns-pct">' + (s.non_moving_pct || 0) + '%</div>' +
        '</div></div>';

      // Holding Cost Card
      html += '<div class="col-md-3">' +
        '<div class="vsb-fns-card vsb-fns-cost">' +
          '<div class="vsb-fns-icon"><i class="fa fa-inr"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Holding Cost</div>' +
            '<div class="vsb-fns-value">' + formatCurrency(s.total_holding_cost || 0) + '</div>' +
            '<div class="vsb-fns-sub">@ ' + (s.holding_cost_rate_pct || 12) + '% p.a. · ' + (s.total_items || 0) + ' items</div>' +
          '</div>' +
          '<div class="vsb-fns-pct"><i class="fa fa-info-circle" style="font-size:14px; cursor:help;" ' +
            'title="Holding Cost = Stock Value × Avg Age Days × Annual Rate / 365"></i></div>' +
        '</div></div>';

      html += '</div>';

      // ── FNS Distribution Bar ──
      var fastW = s.fast_pct || 0;
      var slowW = s.slow_pct || 0;
      var nmW   = s.non_moving_pct || 0;
      html += '<div class="vsb-fns-bar-wrap">' +
        '<div class="vsb-fns-bar">' +
          '<div class="vsb-fns-bar-seg vsb-fns-bar-fast" style="width:' + fastW + '%" title="Fast: ' + fastW + '%"></div>' +
          '<div class="vsb-fns-bar-seg vsb-fns-bar-slow" style="width:' + slowW + '%" title="Slow: ' + slowW + '%"></div>' +
          '<div class="vsb-fns-bar-seg vsb-fns-bar-nm" style="width:' + nmW + '%" title="Non-moving: ' + nmW + '%"></div>' +
        '</div>' +
        '<div class="vsb-fns-bar-legend">' +
          '<span><span class="vsb-fns-dot" style="background:#10B981;"></span> Fast</span>' +
          '<span><span class="vsb-fns-dot" style="background:#F59E0B;"></span> Slow</span>' +
          '<span><span class="vsb-fns-dot" style="background:#EF4444;"></span> Non-moving</span>' +
        '</div>' +
      '</div>';

      // ── FNS Detail Table ──
      if (cats.length > 0) {
        html += '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-table"></i> Category × Branch Classification (' + cats.length + ' rows)</div>' +
          '<div class="table-responsive"><table class="table vsb-table" id="vsbFNSTable"><thead><tr>' +
            '<th>Category</th><th>Branch</th><th>Class</th>' +
            '<th class="text-right">In Stock</th><th class="text-right">Value</th>' +
            '<th class="text-right">Sold (' + days + 'd)</th><th class="text-right">Turnover</th>' +
            '<th class="text-right">Avg Age</th><th class="text-right">Holding Cost</th>' +
          '</tr></thead><tbody>';

        cats.forEach(function (c) {
          var badge = '';
          if (c.classification === 'Fast') {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-fast"><i class="fa fa-rocket"></i> Fast</span>';
          } else if (c.classification === 'Slow') {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-slow"><i class="fa fa-hourglass-half"></i> Slow</span>';
          } else {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-nm"><i class="fa fa-ban"></i> Non-moving</span>';
          }

          html += '<tr>' +
            '<td><strong>' + esc(c.category) + '</strong></td>' +
            '<td>' + esc(c.branch_name) + '</td>' +
            '<td>' + badge + '</td>' +
            '<td class="text-right">' + c.in_stock_count + '</td>' +
            '<td class="text-right">' + formatCurrency(c.in_stock_value) + '</td>' +
            '<td class="text-right">' + c.sold_in_period + '</td>' +
            '<td class="text-right">' + (c.turnover_rate * 100).toFixed(1) + '%</td>' +
            '<td class="text-right">' + c.avg_age_days + 'd</td>' +
            '<td class="text-right text-danger">' + formatCurrency(c.holding_cost) + '</td>' +
          '</tr>';
        });

        html += '</tbody></table></div></div>';
      }

      $("#vsbFNSClassificationContent").html(html).show();
    });
  }



  // ═══════════════════════════════════════════
  // Stock Availability Board
  // ═══════════════════════════════════════════
  function loadStockAvailability() {
    showLoading("StockAvailability");

    fetchAnalytics("branch-distribution", {}, function (err, data) {
      hideLoading("StockAvailability");

      if (err || !data || !data.branches) {
        showError("StockAvailability", err || "No data returned");
        return;
      }

      var branches = data.branches || [];
      loadedBoards["stock-availability"] = true;

      // KPIs
      var totalItems = 0, totalValue = 0, totalWeight = 0;
      branches.forEach(function (b) {
        totalItems += b.total_items || 0;
        totalValue += b.estimated_value || 0;
        totalWeight += b.total_weight_gm || 0;
      });

      var kpiHtml = '<div class="row">';
      kpiHtml += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-primary">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-cubes"></i></div>' +
          '<div class="vsb-kpi-value">' + totalItems + '</div>' +
          '<div class="vsb-kpi-label">Total In-Stock Items</div>' +
        '</div></div>';
      kpiHtml += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-success">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-inr"></i></div>' +
          '<div class="vsb-kpi-value">' + formatCurrency(totalValue) + '</div>' +
          '<div class="vsb-kpi-label">Total Stock Value</div>' +
        '</div></div>';
      kpiHtml += '<div class="col-md-4">' +
        '<div class="vsb-kpi-card vsb-kpi-warning">' +
          '<div class="vsb-kpi-icon"><i class="fa fa-balance-scale"></i></div>' +
          '<div class="vsb-kpi-value">' + (totalWeight / 1000).toFixed(1) + ' kg</div>' +
          '<div class="vsb-kpi-label">Total Weight</div>' +
        '</div></div>';
      kpiHtml += '</div>';

      // Branch Cards — injected into the right column
      var cardsHtml = '';
      branches.forEach(function (b) {
        var pct = totalItems > 0 ? Math.round((b.total_items / totalItems) * 100) : 0;

        cardsHtml += '<div class="vsb-branch-card">' +
            '<div class="vsb-branch-header">' +
              '<div class="vsb-branch-name"><i class="fa fa-map-marker"></i> ' + esc(b.name) + '</div>' +
              '<div class="vsb-branch-pct">' + pct + '%</div>' +
            '</div>' +
            '<div class="vsb-branch-stats">' +
              '<div class="vsb-branch-stat">' +
                '<div class="vsb-branch-stat-val">' + (b.total_items || 0) + '</div>' +
                '<div class="vsb-branch-stat-lbl">Pieces</div>' +
              '</div>' +
              '<div class="vsb-branch-stat">' +
                '<div class="vsb-branch-stat-val">' + formatCurrency(b.estimated_value || 0) + '</div>' +
                '<div class="vsb-branch-stat-lbl">Value</div>' +
              '</div>' +
              '<div class="vsb-branch-stat">' +
                '<div class="vsb-branch-stat-val">' + ((b.total_weight_gm || 0) / 1000).toFixed(2) + ' kg</div>' +
                '<div class="vsb-branch-stat-lbl">Weight</div>' +
              '</div>' +
            '</div>';

        // Metal breakdown
        if (b.by_metal && b.by_metal.length > 0) {
          cardsHtml += '<div class="vsb-branch-breakdown">';
          b.by_metal.forEach(function (m) {
            cardsHtml += '<span class="vsb-badge vsb-badge-outline">' +
              esc(m.metal) + ': ' + m.count + ' pcs</span> ';
          });
          cardsHtml += '</div>';
        }

        // Top categories
        if (b.by_category && b.by_category.length > 0) {
          cardsHtml += '<div class="vsb-branch-categories">';
          b.by_category.slice(0, 5).forEach(function (c) {
            cardsHtml += '<span class="vsb-cat-chip">' + esc(c.name) + ' (' + c.count + ')</span> ';
          });
          cardsHtml += '</div>';
        }

        cardsHtml += '</div>';
      });

      // Insert: KPIs before layout row, branch cards into right column
      $("#vsbStockAvailabilityContent").find(".vsb-kpi-row").remove();
      $(".vsb-stock-layout").before('<div class="vsb-kpi-row">' + kpiHtml + '</div>');
      $("#vsbBranchCardsColumn").html(cardsHtml);
      $("#vsbStockAvailabilityContent").show();

      // Initialize jVectorMap India (if available)
      initIndiaMap(branches, totalItems);
    });
  }

  // ═══════════════════════════════════════════
  // India Map — jVectorMap initialization (uses world map zoomed to India)
  // ═══════════════════════════════════════════
  function initIndiaMap(branches, totalItems) {
    if (typeof $.fn.vectorMap === "undefined") {
      console.warn("[BOARDS] jVectorMap not loaded, skipping map");
      return;
    }

    var $mapCard = $("#vsbMapCard");
    var $mapEl = $("#vsbIndiaMap");

    // Clean up any existing map instance
    $mapEl.empty();

    // Build markers from branch data — coords come from the API
    // (resolved via city table + INDIAN_CITY_COORDS in WinPixel backend)
    var markers = [];
    var markerValues = {};
    var coordCounts = {}; // track how many markers share the same point

    branches.forEach(function (b) {
      var lat = b.latitude;
      var lng = b.longitude;

      // Skip branches without coordinates — don't fake positions
      if (lat == null || lng == null) {
        console.warn("[BOARDS] No coords for branch:", b.name, "— add city to INDIAN_CITY_COORDS");
        return;
      }

      // Offset overlapping markers in a radial pattern around the city center
      var coordKey = lat.toFixed(2) + "," + lng.toFixed(2);
      var count = coordCounts[coordKey] || 0;
      coordCounts[coordKey] = count + 1;

      if (count > 0) {
        // Spread markers in a circle (0.08° ≈ 9km radius)
        var angle = (count - 1) * (2 * Math.PI / 8); // 8 positions max per ring
        var radius = 0.08 * Math.ceil(count / 8);     // expand rings if > 8
        lat += radius * Math.cos(angle);
        lng += radius * Math.sin(angle);
      }

      var idx = markers.length;
      markers.push({
        latLng: [lat, lng],
        name: b.name + " — " + (b.total_items || 0) + " items"
      });
      markerValues[idx] = b.total_items || 0;
    });

    if (markers.length === 0) return;

    $mapCard.show();

    try {
      $mapEl.vectorMap({
        map: "world_mill_en",
        backgroundColor: "transparent",
        focusOn: {
          x: 0.71,
          y: 0.42,
          scale: 15
        },
        zoomOnScroll: false,
        panOnDrag: true,
        zoomButtons: true,
        zoomMax: 30,
        zoomMin: 1,
        regionStyle: {
          initial: {
            fill: "#E2E8F0",
            "fill-opacity": 1,
            stroke: "#CBD5E1",
            "stroke-width": 0.5
          },
          hover: {
            fill: "#4F46E5",
            "fill-opacity": 0.6
          }
        },
        markerStyle: {
          initial: {
            r: 6,
            fill: "#10B981",
            "fill-opacity": 0.9,
            stroke: "#fff",
            "stroke-width": 1.5
          },
          hover: {
            r: 10,
            stroke: "#FCD34D",
            "stroke-width": 2
          }
        },
        markers: markers,
        series: {
          markers: [{
            attribute: "fill",
            scale: ["#EF4444", "#F59E0B", "#10B981"],
            values: markerValues,
            min: 0,
            max: Math.max.apply(null, Object.values(markerValues)) || 100
          }, {
            attribute: "r",
            scale: [5, 15],
            values: markerValues,
            min: 0,
            max: Math.max.apply(null, Object.values(markerValues)) || 100
          }]
        }
      });
    } catch (e) {
      console.warn("[BOARDS] Map init error:", e);
      $mapCard.hide();
    }
  }

  // ═══════════════════════════════════════════
  // Design Performance Board
  // ═══════════════════════════════════════════
  function loadDesignPerformance(days) {
    showLoading("DesignPerformance");
    var months = Math.max(1, Math.round(days / 30));

    fetchAnalytics("design-performance", { months: months }, function (err, data) {
      hideLoading("DesignPerformance");

      if (err || !data) {
        showError("DesignPerformance", err || "No data returned");
        return;
      }

      loadedBoards["design-performance"] = true;
      var topDesigns = data.top_designs || [];
      var catVelocity = data.category_velocity || [];

      var html = '';

      // Top Designs Table
      html += '<div class="vsb-table-card">' +
        '<div class="vsb-table-title"><i class="fa fa-trophy"></i> Top Selling Designs (' + months + ' months)</div>' +
        '<div class="table-responsive">' +
        '<table class="table table-hover vsb-table">' +
        '<thead><tr>' +
          '<th>#</th><th>Design</th><th>Category</th><th>Sold</th>' +
          '<th>Revenue</th><th>Avg Price</th><th>Customers</th>' +
        '</tr></thead><tbody>';

      topDesigns.forEach(function (d, i) {
        html += '<tr>' +
          '<td>' + (i + 1) + '</td>' +
          '<td><strong>' + esc(d.design_name || d.design_code) + '</strong></td>' +
          '<td>' + esc(d.category) + '</td>' +
          '<td>' + (d.items_sold || 0) + '</td>' +
          '<td>' + formatCurrency(d.revenue || 0) + '</td>' +
          '<td>' + formatCurrency(d.avg_price || 0) + '</td>' +
          '<td>' + (d.unique_customers || 0) + '</td>' +
        '</tr>';
      });

      if (topDesigns.length === 0) {
        html += '<tr><td colspan="7" class="vsb-empty">No design data available</td></tr>';
      }

      html += '</tbody></table></div></div>';

      // Category Velocity Table
      html += '<div class="vsb-table-card">' +
        '<div class="vsb-table-title"><i class="fa fa-line-chart"></i> Category Velocity</div>' +
        '<div class="table-responsive">' +
        '<table class="table table-hover vsb-table">' +
        '<thead><tr>' +
          '<th>Category</th><th>Items Sold</th><th>Revenue</th>' +
          '<th>Designs Sold</th><th>Items/Month</th>' +
        '</tr></thead><tbody>';

      catVelocity.forEach(function (c) {
        html += '<tr>' +
          '<td><strong>' + esc(c.category) + '</strong></td>' +
          '<td>' + (c.items_sold || 0) + '</td>' +
          '<td>' + formatCurrency(c.revenue || 0) + '</td>' +
          '<td>' + (c.designs_sold || 0) + '</td>' +
          '<td><strong>' + (c.items_per_month || 0) + '</strong></td>' +
        '</tr>';
      });

      if (catVelocity.length === 0) {
        html += '<tr><td colspan="5" class="vsb-empty">No category data available</td></tr>';
      }

      html += '</tbody></table></div></div>';

      $("#vsbDesignPerformanceContent").html(html).show();
    });
  }

  // ═══════════════════════════════════════════
  // Age Analysis Board
  // ═══════════════════════════════════════════
  function loadAgeAnalysis(days) {
    showLoading("AgeAnalysis");

    // Load both age-distribution and cross-branch-aging in parallel
    var ageData = null, crossData = null;
    var done = 0;

    function checkDone() {
      done++;
      if (done < 2) return;
      hideLoading("AgeAnalysis");
      renderAgeAnalysis(ageData, crossData);
    }

    fetchAnalytics("age-distribution", { days_threshold: days, age_type: getAgeType() }, function (err, data) {
      if (!err && data) ageData = data;
      checkDone();
    });

    fetchAnalytics("cross-branch-aging", { days_threshold: days, age_type: getAgeType() }, function (err, data) {
      if (!err && data) crossData = data;
      checkDone();
    });
  }

  function renderAgeAnalysis(ageData, crossData) {
    loadedBoards["age-analysis"] = true;
    var html = '';

    // Age Distribution — Chart.js horizontal bar
    if (ageData && ageData.buckets) {
      var buckets = ageData.buckets;
      var barColors = ['#10B981', '#3B82F6', '#F59E0B', '#F97316', '#EF4444', '#991B1B'];

      html += '<div class="row">' +
        '<div class="col-md-7">' +
        '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-bar-chart"></i> Inventory Age Distribution</div>' +
          '<div class="vsb-chart-container" style="position:relative; height:280px; padding:8px 12px;">' +
            '<canvas id="vsbAgeChartCanvas"></canvas>' +
          '</div>' +
        '</div>' +
        '</div>';

      // Age summary stats + priority actions (right column)
      var summary = ageData.summary || {};
      var totalItems = parseInt(summary.total_tags) || 0;
      var avgAge = parseInt(summary.avg_age_days) || 0;

      // Compute at-risk items (91-180 days)
      var atRiskCount = 0;
      var deadCount = 0;
      var freshCount = 0;
      buckets.forEach(function (b) {
        var label = b.age_bucket || b.label || '';
        var cnt = parseInt(b.tag_count || b.count || 0);
        if (label.indexOf('0-30') >= 0) freshCount = cnt;
        else if (label.indexOf('91') >= 0 && label.indexOf('120') >= 0) atRiskCount += cnt;
        else if (label.indexOf('121') >= 0 || label.indexOf('181') >= 0 || label.indexOf('241') >= 0 || label.indexOf('365') >= 0) deadCount += cnt;
      });

      html += '<div class="col-md-5">' +
        '<div class="vsb-table-card vsb-priority-panel">' +
          '<div class="vsb-table-title"><i class="fa fa-list-ol"></i> Priority Actions</div>' +
          '<div class="vsb-priority-list">';

      // Action 1: Dead stock (>180d)
      if (deadCount > 0) {
        html += '<div class="vsb-priority-item vsb-priority-critical">' +
          '<div class="vsb-priority-dot"></div>' +
          '<div class="vsb-priority-body">' +
            '<div class="vsb-priority-action">Pause new purchases for aged categories</div>' +
            '<div class="vsb-priority-detail">' + deadCount + ' items aged >180 days — consider melt/return</div>' +
          '</div>' +
        '</div>';
      }

      // Action 2: At-risk stock (91-180d)
      if (atRiskCount > 0) {
        html += '<div class="vsb-priority-item vsb-priority-warning">' +
          '<div class="vsb-priority-dot"></div>' +
          '<div class="vsb-priority-body">' +
            '<div class="vsb-priority-action">Run targeted promotions on at-risk stock</div>' +
            '<div class="vsb-priority-detail">' + atRiskCount + ' items in 91-180 day zone — run clearance before they become aged</div>' +
          '</div>' +
        '</div>';
      }

      // Action 3: Fresh stock
      html += '<div class="vsb-priority-item vsb-priority-info">' +
        '<div class="vsb-priority-dot"></div>' +
        '<div class="vsb-priority-body">' +
          '<div class="vsb-priority-action">Increase fresh arrivals to improve ratio</div>' +
          '<div class="vsb-priority-detail">Fresh stock: ' + freshCount + ' | Avg age: ' + avgAge + ' days | Target fresh:aged ≥ 1:1</div>' +
        '</div>' +
      '</div>';

      html += '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

      // Deferred Chart.js render (after DOM insert)
      setTimeout(function () {
        var ctx = document.getElementById("vsbAgeChartCanvas");
        if (!ctx || !ChartJS) return;

        var labels = [];
        var dataVals = [];
        var bgColors = [];

        buckets.forEach(function (b, i) {
          labels.push(b.age_bucket || b.label || b.range || '');
          dataVals.push(parseInt(b.tag_count || b.count || 0));
          bgColors.push(barColors[i % barColors.length]);
        });

        new ChartJS(ctx, {
          type: "bar",
          data: {
            labels: labels,
            datasets: [{
              label: "Items",
              data: dataVals,
              backgroundColor: bgColors,
              borderRadius: 6,
              barPercentage: 0.7,
              categoryPercentage: 0.85
            }]
          },
          options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  afterLabel: function (ctx) {
                    var b = buckets[ctx.dataIndex];
                    return formatCurrency(b.total_value || b.value || 0) + " value";
                  }
                }
              }
            },
            scales: {
              x: {
                grid: { display: false },
                ticks: {
                  font: { family: "Inter", size: 11 },
                  color: "#94A3B8"
                }
              },
              y: {
                grid: { display: false },
                ticks: {
                  font: { family: "Inter", size: 11, weight: 600 },
                  color: "#475569"
                }
              }
            }
          }
        });
      }, 100);
    }

    // Cross-Branch Aging
    if (crossData && crossData.items && crossData.items.length > 0) {
      var items = crossData.items;

      html += '<div class="vsb-table-card vsb-card-skip">' +
        '<div class="vsb-table-title"><i class="fa fa-exclamation-circle"></i> Cross-Branch Aged Stock (' + items.length + ' categories)</div>' +
        '<div class="table-responsive">' +
        '<table class="table table-hover vsb-table">' +
        '<thead><tr>' +
          '<th>Category</th><th>Branches</th><th>Total Items</th>' +
          '<th>Total Value</th><th>Avg Age</th>' +
        '</tr></thead><tbody>';

      items.forEach(function (item) {
        var branchList = (item.branches || []).map(function (b) {
          return esc(b.name || b.branch) + ' (' + (b.pcs || b.count || 0) + ')';
        }).join(', ');

        html += '<tr>' +
          '<td><strong>' + esc(item.category) + '</strong></td>' +
          '<td>' + branchList + '</td>' +
          '<td>' + (item.total_pcs || item.total_count || 0) + '</td>' +
          '<td>' + formatCurrency(item.total_value || 0) + '</td>' +
          '<td>' + Math.round(item.avg_age_days || item.avg_age || 0) + 'd</td>' +
        '</tr>';
      });

      html += '</tbody></table></div></div>';
    } else if (!crossData) {
      html += '<div class="vsb-empty">Cross-branch aging data not available</div>';
    }

    if (!html) {
      html = '<div class="vsb-empty">No age analysis data available</div>';
    }

    $("#vsbAgeAnalysisContent").html(html).show();
  }

  // ═══════════════════════════════════════════
  // Helpers
  // ═══════════════════════════════════════════
  function showLoading(boardId) {
    $("#vsb" + boardId + "Loading").show();
    $("#vsb" + boardId + "Content").hide();
  }

  function hideLoading(boardId) {
    $("#vsb" + boardId + "Loading").hide();
  }

  function showError(boardId, msg) {
    var html = '<div class="vsb-error">' +
      '<i class="fa fa-exclamation-circle"></i> ' +
      '<span>' + esc(msg || "Failed to load data") + '</span>' +
    '</div>';
    $("#vsb" + boardId + "Content").html(html).show();
  }

  function formatCurrency(val) {
    if (!val || isNaN(val)) return "₹0";
    var num = parseFloat(val);
    if (num >= 10000000) return "₹" + (num / 10000000).toFixed(1) + "Cr";
    if (num >= 100000) return "₹" + (num / 100000).toFixed(1) + "L";
    if (num >= 1000) return "₹" + (num / 1000).toFixed(1) + "K";
    return "₹" + num.toFixed(0);
  }

  function esc(s) {
    if (!s) return "";
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }

  // ═══════════════════════════════════════════
  // AI Suggestions Board
  // ═══════════════════════════════════════════
  var _aiLoaded = false;

  function loadAISuggestions(days) {
    if (_aiLoaded) return;
    showLoading("AISuggestions");

    fetchAnalytics("board-insights", { days: days, age_type: getAgeType() }, function (err, data) {
      hideLoading("AISuggestions");

      if (err || !data || !data.sections) {
        $("#vsbAISuggestionsContent").html(
          '<div class="vsb-empty"><i class="fa fa-exclamation-circle"></i> ' +
          'Failed to load AI insights. ' + (err || 'No data') + '</div>'
        ).show();
        return;
      }

      _aiLoaded = true;
      renderAISections(data);

      // Reuse same response for executive panel, KPI strip, and comparison
      if (data.health_score) {
        renderExecutivePanel(data);
      }
      if (data.kpi_summary) {
        renderKPIStrip(data.kpi_summary);
        renderAlertBanner(data.kpi_summary);
      }
      if (data.sections) {
        renderComparisonPanel(data);
        initFooterBar();
      }
    });
  }

  function renderAISections(data) {
    var sections = data.sections || [];
    var summary = data.summary || {};

    // ── Executive Panel (renders above tabs, always visible) ──
    renderExecutivePanel(data);

    if (sections.length === 0) {
      $("#vsbAISuggestionsContent").html(
        '<div class="vsb-empty"><i class="fa fa-check-circle"></i> ' +
        'No actionable insights at this time. Everything looks healthy!</div>'
      ).show();
      return;
    }

    // ── Summary chips strip ──
    var html = '<div class="ai2-summary-strip">';
    html += '<div class="ai2-summary-chip ai2-chip-critical">' +
      '<i class="fa fa-exclamation-circle"></i> ' +
      (summary.critical || 0) + ' Critical</div>';
    html += '<div class="ai2-summary-chip ai2-chip-warning">' +
      '<i class="fa fa-warning"></i> ' +
      (summary.warning || 0) + ' Warnings</div>';
    html += '<div class="ai2-summary-chip ai2-chip-info">' +
      '<i class="fa fa-info-circle"></i> ' +
      (summary.info || 0) + ' Info</div>';
    html += '<div class="ai2-summary-chip ai2-chip-total">' +
      '<i class="fa fa-th-list"></i> ' +
      (summary.total_cards || 0) + ' Total</div>';
    html += '</div>';

    // ── Sub-tab bar ──
    html += '<div class="ai2-subtabs">';
    for (var i = 0; i < sections.length; i++) {
      var sec = sections[i];
      var activeClass = i === 0 ? ' ai2-subtab-active' : '';
      html += '<button class="ai2-subtab' + activeClass + '" data-ai-section="' + i + '">';
      html += '<i class="fa ' + esc(sec.icon || 'fa-lightbulb-o') + '"></i> ';
      html += esc(sec.title);
      html += ' <span class="ai2-subtab-count">' + (sec.cards || []).length + '</span>';
      html += '</button>';
    }
    html += '</div>';

    // ── Section content panels ──
    for (var i = 0; i < sections.length; i++) {
      var sec = sections[i];
      var cards = sec.cards || [];
      var displayStyle = i === 0 ? '' : ' style="display:none;"';

      html += '<div class="ai2-panel" data-ai-panel="' + i + '"' + displayStyle + '>';

      // Section description
      if (sec.description) {
        html += '<div class="ai2-panel-desc"><i class="fa fa-info-circle"></i> ' + esc(sec.description) + '</div>';
      }

      // Cards as compact rows
      for (var j = 0; j < cards.length; j++) {
        html += renderInsightCard(cards[j]);
      }

      html += '</div>';
    }

    $("#vsbAISuggestionsContent").html(html).show();

    // Sub-tab switching
    $(document).off("click.ai2subtab").on("click.ai2subtab", ".ai2-subtab", function () {
      var idx = $(this).data("ai-section");
      $(".ai2-subtab").removeClass("ai2-subtab-active");
      $(this).addClass("ai2-subtab-active");
      $(".ai2-panel").hide();
      $('[data-ai-panel="' + idx + '"]').fadeIn(200);
    });

    // CTA click handlers — switch to referenced board tab
    $(document).off("click.aicta").on("click.aicta", ".ai2-card-cta", function () {
      var board = $(this).data("board");
      if (board) {
        $(".vsb-utab").removeClass("active");
        $(".vsb-utab[data-board='" + board + "']").addClass("active");
        $(".vsb-board").hide();
        $("#vsbComparisonPanel").hide();
        $("#vsbFooterBar").hide();
        $("#vsb-" + board).show();
        loadBoard(board);
      }
    });

    // Expand/collapse card details
    $(document).off("click.ai2toggle").on("click.ai2toggle", ".ai2-card-toggle", function () {
      var $card = $(this).closest(".ai2-card");
      $card.toggleClass("ai2-card-expanded");
      var $detail = $card.find(".ai2-card-detail");
      if ($card.hasClass("ai2-card-expanded")) {
        $detail.slideDown(200);
        $(this).find("i").removeClass("fa-chevron-down").addClass("fa-chevron-up");
      } else {
        $detail.slideUp(200);
        $(this).find("i").removeClass("fa-chevron-up").addClass("fa-chevron-down");
      }
    });
  }

  // ═══════════════════════════════════════════
  // Executive Panel — Health Score + Summary
  // ═══════════════════════════════════════════

  function renderExecutivePanel(data) {
    var $gauge = $("#vsbStripGauge");
    var hs = data.health_score;

    if (!hs) {
      $gauge.empty();
      return;
    }

    var score = hs.score || 0;
    var grade = hs.grade || "?";
    var color = hs.color || "amber";
    var headline = hs.headline || "";

    // SVG circular gauge — circumference of r=18 circle = 2πr ≈ 113.1
    var circ = 113.1;
    var offset = circ - (circ * score / 100);
    var gaugeColor = "gauge-" + color;

    var h = '';
    h += '<div class="vsb-strip-gauge-ring">';
    h += '<svg viewBox="0 0 44 44">';
    h += '<circle class="gauge-bg" cx="22" cy="22" r="18"></circle>';
    h += '<circle class="gauge-fill ' + gaugeColor + '" cx="22" cy="22" r="18" ' +
         'stroke-dasharray="' + circ + '" stroke-dashoffset="' + offset + '"></circle>';
    h += '</svg>';
    h += '<div class="vsb-strip-gauge-score">' + score + '</div>';
    h += '</div>';
    h += '<div class="vsb-strip-gauge-meta">';
    h += '<div class="vsb-strip-gauge-headline">' + esc(headline).substring(0, 40) + '</div>';
    h += '<span class="vsb-strip-gauge-grade vsb-strip-grade-' + grade + '">Grade ' + grade + '</span>';
    h += '</div>';

    $gauge.html(h);
    $("#vsbDashboardStrip").fadeIn(300);
  }

  function renderInsightCard(card) {
    var sev = card.severity || 'info';
    var h = '<div class="ai2-card ai2-card-' + sev + '">';

    // ── Row header (always visible) ──
    h += '<div class="ai2-card-row">';

    // Icon
    h += '<div class="ai2-card-icon"><i class="fa ' + esc(card.icon || 'fa-lightbulb-o') + '"></i></div>';

    // Title + severity
    h += '<div class="ai2-card-main">';
    h += '<div class="ai2-card-title">' + esc(card.title) + '</div>';
    h += '<div class="ai2-card-body-preview">' + esc(card.body || '').substring(0, 100) + '</div>';
    h += '</div>';

    // Severity badge
    h += '<div class="ai2-sev ai2-sev-' + sev + '">' + sev.toUpperCase() + '</div>';

    // Inline metrics (compact)
    var metrics = card.metrics || [];
    if (metrics.length > 0) {
      h += '<div class="ai2-card-metrics-inline">';
      for (var m = 0; m < Math.min(metrics.length, 3); m++) {
        var met = metrics[m];
        h += '<div class="ai2-metric-inline">';
        h += '<span class="ai2-metric-val">' + esc(met.value) + '</span>';
        if (met.trend === 'up') h += '<i class="fa fa-arrow-up ai2-trend-up"></i>';
        else if (met.trend === 'down') h += '<i class="fa fa-arrow-down ai2-trend-down"></i>';
        h += '<span class="ai2-metric-lbl">' + esc(met.label) + '</span>';
        h += '</div>';
      }
      h += '</div>';
    }

    // CTA button inline
    if (card.action && card.action.label) {
      h += '<button class="ai2-card-cta" data-board="' +
        esc(card.action.board || '') + '">' +
        esc(card.action.label) + ' →</button>';
    }

    // Expand toggle
    h += '<button class="ai2-card-toggle" title="Show details"><i class="fa fa-chevron-down"></i></button>';

    h += '</div>'; // close row

    // ── Detail panel (hidden by default) ──
    h += '<div class="ai2-card-detail" style="display:none;">';

    // Full body text
    if (card.body) {
      h += '<div class="ai2-card-body-full">' + esc(card.body) + '</div>';
    }

    // Recommendation
    if (card.recommendation) {
      h += '<div class="ai2-card-rec">' +
        '<i class="fa fa-lightbulb-o"></i> ' +
        '<span>' + esc(card.recommendation) + '</span>' +
        '</div>';
    }

    // All metrics (full)
    if (metrics.length > 0) {
      h += '<div class="ai2-card-metrics-full">';
      for (var m = 0; m < metrics.length; m++) {
        var met = metrics[m];
        h += '<div class="ai2-metric-full">';
        if (met.icon) h += '<i class="fa ' + esc(met.icon) + '"></i> ';
        h += '<span class="ai2-metric-val">' + esc(met.value) + '</span>';
        if (met.trend === 'up') h += '<i class="fa fa-arrow-up ai2-trend-up"></i>';
        else if (met.trend === 'down') h += '<i class="fa fa-arrow-down ai2-trend-down"></i>';
        else if (met.trend === 'stable') h += '<i class="fa fa-minus ai2-trend-stable"></i>';
        h += '<span class="ai2-metric-lbl">' + esc(met.label) + '</span>';
        h += '</div>';
      }
      h += '</div>';
    }

    h += '</div>'; // close detail
    h += '</div>'; // close card
    return h;
  }

  // ═══════════════════════════════════════════
  // Ask AI — Natural Language Query
  // ═══════════════════════════════════════════

  var askAiLoading = false;

  // Submit on Enter or button click
  $("#vsbAskAiInput").on("keydown", function(e) {
    if (e.which === 13 && !askAiLoading) {
      e.preventDefault();
      submitAskAi();
    }
  });

  $("#vsbAskAiBtn").on("click", function() {
    if (!askAiLoading) submitAskAi();
  });

  // Follow-up suggestion click
  $(document).on("click", ".vsb-ask-ai-suggestion a", function(e) {
    e.preventDefault();
    var q = $(this).data("query");
    if (q) {
      $("#vsbAskAiInput").val(q);
      submitAskAi();
    }
  });

  // Close result
  $(document).on("click", ".vsb-ask-ai-close", function() {
    $("#vsbAskAiResult").hide();
  });

  function submitAskAi() {
    var q = $.trim($("#vsbAskAiInput").val());
    if (!q || q.length < 3) return;

    askAiLoading = true;
    var $btn = $("#vsbAskAiBtn");
    var $result = $("#vsbAskAiResult");

    $btn.prop("disabled", true).find(".fa").removeClass("fa-paper-plane").addClass("fa-spinner fa-spin");
    $result.html(
      '<div class="vsb-ask-ai-loading">' +
      '<i class="fa fa-cog"></i> Analysing your question...</div>'
    ).show();

    $.ajax({
      url: base_url + "index.php/admin_visual_search/proxy_analytics",
      type: "POST",
      data: {
        endpoint: "ask-ai",
        params: JSON.stringify({ q: q })
      },
      dataType: "json",
      timeout: 90000,
      success: function(data) {
        if (data && data.status && data.response) {
          renderAskAiResult(data.response);
        } else {
          $result.html(
            '<div class="vsb-ask-ai-answer">Sorry, no results found. Try a different question.</div>'
          );
        }
      },
      error: function(xhr, status, err) {
        $result.html(
          '<div class="vsb-ask-ai-answer" style="color:#f87171;">' +
          '<i class="fa fa-exclamation-triangle"></i> ' +
          'Failed to process your question. Please try again.</div>'
        );
      },
      complete: function() {
        askAiLoading = false;
        $btn.prop("disabled", false).find(".fa").removeClass("fa-spinner fa-spin").addClass("fa-paper-plane");
      }
    });
  }

  function renderAskAiResult(resp) {
    var $result = $("#vsbAskAiResult");
    var h = '';

    // Close button
    h += '<button class="vsb-ask-ai-close" title="Close"><i class="fa fa-times"></i></button>';

    // Title + confidence badge
    h += '<div class="vsb-ask-ai-title">';
    h += '<i class="fa fa-magic"></i> ';
    h += esc(resp.title || 'Result');
    if (resp.confidence && resp.confidence !== 'none') {
      h += ' <span class="vsb-ask-ai-confidence vsb-conf-' + resp.confidence + '">' +
           resp.confidence + ' match</span>';
    }
    h += '</div>';

    // Answer text
    h += '<div class="vsb-ask-ai-answer">' + esc(resp.answer || '') + '</div>';

    // Data table
    var cols = resp.columns || [];
    var rows = resp.data || [];

    if (cols.length > 0 && rows.length > 0) {
      h += '<table class="vsb-ask-ai-table">';
      h += '<thead><tr>';
      for (var c = 0; c < cols.length; c++) {
        h += '<th>' + esc(cols[c]) + '</th>';
      }
      h += '</tr></thead>';
      h += '<tbody>';
      for (var r = 0; r < rows.length; r++) {
        h += '<tr>';
        for (var c2 = 0; c2 < cols.length; c2++) {
          var val = rows[r][cols[c2]];
          h += '<td>' + esc(val !== undefined ? String(val) : '') + '</td>';
        }
        h += '</tr>';
      }
      h += '</tbody></table>';
    }

    // Follow-up suggestion
    if (resp.suggestion) {
      h += '<div class="vsb-ask-ai-suggestion">';
      h += '<i class="fa fa-lightbulb-o"></i> Try: ';
      h += '<a href="#" data-query="' + esc(resp.suggestion) + '">' +
           esc(resp.suggestion) + '</a>';
      h += '</div>';
    }

    $result.html(h).show();
  }

  // ═══════════════════════════════════════════
  // KPI Strip + Alert Banner
  // ═══════════════════════════════════════════

  function renderKPIStrip(kpi) {
    // Total Inventory Value
    $("#vsbStripTotalVal").text(formatCurrency(kpi.total_value));

    // Aged Stock Value
    $("#vsbStripDeadVal").text(formatCurrency(kpi.dead_stock_value));

    // Aged Stock %
    var pct = parseFloat(kpi.dead_stock_pct) || 0;
    $("#vsbStripDeadPctVal").text(pct.toFixed(1) + "%");

    // Minibar color
    var barColor;
    if (pct >= 60) barColor = "#dc2626";
    else if (pct >= 40) barColor = "#d97706";
    else barColor = "#059669";
    $("#vsbStripDeadPctBar").css({
      width: Math.min(pct, 100) + "%",
      background: barColor
    });

    // Fresh : Aged Ratio
    var ratioLabel = kpi.fresh_aged_label || "N/A";
    var ratio = parseFloat(kpi.fresh_aged_ratio) || 0;
    var arrow = "";
    if (ratio >= 1) {
      arrow = ' <i class="fa fa-arrow-up" style="color:#059669; font-size:10px;"></i>';
    } else if (ratio > 0) {
      arrow = ' <i class="fa fa-arrow-down" style="color:#dc2626; font-size:10px;"></i>';
    }
    $("#vsbStripFreshAgedVal").html(esc(ratioLabel) + arrow);

    $("#vsbDashboardStrip").fadeIn(300);
  }

  function renderAlertBanner(kpi) {
    var pct = parseFloat(kpi.dead_stock_pct) || 0;
    var ALERT_THRESHOLD = 50; // show alert if aged stock % > 50%

    if (pct < ALERT_THRESHOLD) {
      $("#vsbStripAlert").hide();
      return;
    }

    var deadVal = formatCurrency(kpi.dead_stock_value);
    var shortMsg = pct.toFixed(0) + "% Aged Stock";
    var fullMsg = pct.toFixed(0) + "% of capital (" + deadVal + ") locked in aged stock (>90 days)";
    $("#vsbStripAlertText").text(shortMsg);
    $("#vsbStripAlert").attr("title", fullMsg);

    // Click: switch to unmoved-stock tab
    $("#vsbStripAlert").off("click").on("click", function () {
      $('[data-board="unmoved-stock"]').trigger("click");
    });

    $("#vsbStripAlert").fadeIn(300);
  }

  // ═══════════════════════════════════════════
  // Comparison Panel + Footer
  // ═══════════════════════════════════════════

  function renderComparisonPanel(data) {
    var sections = data.sections || [];

    // Extract best sellers from velocity/fast-movers section cards
    var bestSellers = [];
    var deadStockItems = [];

    sections.forEach(function (sec) {
      (sec.cards || []).forEach(function (card) {
        if (card.card_type === "fast_mover" || card.card_type === "refill") {
          bestSellers.push({
            name: card.title.replace(/^#\d+ /, ""),
            stat: (card.metrics && card.metrics[0]) ? card.metrics[0].value : "",
            icon: card.icon || "fa-rocket"
          });
        }
        if (card.card_type === "dead_stock") {
          deadStockItems.push({
            name: card.title.replace(/^#\d+ /, ""),
            stat: (card.metrics && card.metrics[2]) ? card.metrics[2].value : "",
            icon: card.icon || "fa-ban"
          });
        }
      });
    });

    // Only show if we have data on either side
    if (bestSellers.length === 0 && deadStockItems.length === 0) return;

    // Render best sellers (left column)
    var bsHtml = "";
    bestSellers.slice(0, 5).forEach(function (item, i) {
      bsHtml += '<div class="vsb-compare-row">' +
        '<div class="vsb-compare-rank">' + (i + 1) + '</div>' +
        '<div class="vsb-compare-name">' + esc(item.name) + '</div>' +
        '<div class="vsb-compare-stat">' + esc(item.stat) + '</div>' +
      '</div>';
    });
    if (!bsHtml) bsHtml = '<div style="padding:16px;color:#94a3b8;text-align:center;font-size:12px;">No best seller data yet</div>';
    $("#vsbBestSellersList").html(bsHtml);

    // Render dead stock (right column)
    var dsHtml = "";
    deadStockItems.slice(0, 5).forEach(function (item, i) {
      dsHtml += '<div class="vsb-compare-row">' +
        '<div class="vsb-compare-rank">' + (i + 1) + '</div>' +
        '<div class="vsb-compare-name">' + esc(item.name) + '</div>' +
        '<div class="vsb-compare-stat">' + esc(item.stat) + '</div>' +
      '</div>';
    });
    if (!dsHtml) dsHtml = '<div style="padding:16px;color:#94a3b8;text-align:center;font-size:12px;">No aged stock data yet</div>';
    $("#vsbDeadStockList").html(dsHtml);

    $("#vsbComparisonPanel").fadeIn(300);
  }

  function initFooterBar() {
    // Run Age Analysis — scroll to age analysis tab
    $("#vsbFooterAgeBtn").on("click", function () {
      $('[data-board="age-analysis"]').trigger("click");
      $("html, body").animate({
        scrollTop: $(".vsb-tab-wrap").offset().top - 60
      }, 400);
    });

    // Suggest Promotions — show toast
    $("#vsbFooterPromoBtn").on("click", function () {
      showNotification("info",
        "Promotions Suggested",
        "Consider running 20-30% clearance on items aged 91-180 days. Focus on categories with lowest turnover."
      );
    });

    // Notify Branches — show toast
    $("#vsbFooterNotifyBtn").on("click", function () {
      showNotification("info",
        "Branch Notification",
        "Send aging reports to branch managers highlighting their top 5 slow-moving categories for immediate action."
      );
    });

    $("#vsbFooterBar").fadeIn(300);
  }

  // Simple notification toast helper
  function showNotification(type, title, message) {
    var $toast = $('<div class="vsb-toast vsb-toast-' + type + '">' +
      '<div class="vsb-toast-title"><i class="fa fa-info-circle"></i> ' + esc(title) + '</div>' +
      '<div class="vsb-toast-msg">' + esc(message) + '</div>' +
    '</div>');
    $("body").append($toast);
    setTimeout(function () { $toast.addClass("vsb-toast-show"); }, 50);
    setTimeout(function () {
      $toast.removeClass("vsb-toast-show");
      setTimeout(function () { $toast.remove(); }, 300);
    }, 5000);
  }

  // ═══════════════════════════════════════════
  // Cross-Branch Intel Board (Phase 3)
  // ═══════════════════════════════════════════
  function loadCrossBranchIntel(days) {
    showLoading("CrossBranchIntel");

    fetchAnalytics("product-transfer-candidates", {
      days: days,
      age_type: getAgeType(),
      min_age_days: 60
    }, function (err, data) {
      hideLoading("CrossBranchIntel");

      if (err || !data) {
        showError("CrossBranchIntel", err || "No data returned");
        return;
      }

      loadedBoards["cross-branch-intel"] = true;

      var transfers = data.transfers || [];
      var summary = data.summary || {};
      var html = "";

      // KPI Summary
      html += '<div class="row" style="margin-bottom:16px;">';
      html += '<div class="col-md-4">' +
        '<div class="vsb-fns-card vsb-fns-slow">' +
          '<div class="vsb-fns-icon"><i class="fa fa-exchange"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Transfer Candidates</div>' +
            '<div class="vsb-fns-value">' + (summary.total_candidates || 0) + ' products</div>' +
          '</div>' +
        '</div></div>';

      html += '<div class="col-md-4">' +
        '<div class="vsb-fns-card vsb-fns-nonmoving">' +
          '<div class="vsb-fns-icon"><i class="fa fa-archive"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Stale Value (Recoverable)</div>' +
            '<div class="vsb-fns-value">' + formatCurrency(summary.total_stale_value || 0) + '</div>' +
          '</div>' +
        '</div></div>';

      html += '<div class="col-md-4">' +
        '<div class="vsb-fns-card vsb-fns-fast">' +
          '<div class="vsb-fns-icon"><i class="fa fa-line-chart"></i></div>' +
          '<div class="vsb-fns-body">' +
            '<div class="vsb-fns-title">Proven Demand Revenue</div>' +
            '<div class="vsb-fns-value">' + formatCurrency(summary.total_demand_revenue || 0) + '</div>' +
          '</div>' +
        '</div></div>';
      html += '</div>';

      // Transfer table
      if (transfers.length > 0) {
        html += '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-exchange"></i> Transfer Suggestions — Product Level (' + transfers.length + ')</div>' +
          '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
            '<th>Product</th><th>Category</th>' +
            '<th>From Branch</th><th>To Branch</th>' +
            '<th class="text-right">Stale Qty</th><th class="text-right">Stale Value</th>' +
            '<th class="text-right">Age</th>' +
            '<th class="text-right">Demand (sold)</th><th class="text-right">Transfer Qty</th>' +
          '</tr></thead><tbody>';

        transfers.forEach(function (t) {
          html += '<tr>' +
            '<td><strong>' + esc(t.product_name) + '</strong></td>' +
            '<td><span class="vsb-fns-badge vsb-fns-badge-slow">' + esc(t.category) + '</span></td>' +
            '<td><span class="text-danger"><i class="fa fa-sign-out"></i> ' + esc(t.from_branch) + '</span></td>' +
            '<td><span class="text-success"><i class="fa fa-sign-in"></i> ' + esc(t.to_branch) + '</span></td>' +
            '<td class="text-right">' + t.stale_count + '</td>' +
            '<td class="text-right text-danger">' + formatCurrency(t.stale_value) + '</td>' +
            '<td class="text-right">' + t.stale_age_days + 'd</td>' +
            '<td class="text-right text-success">' + t.demand_sold + ' pcs</td>' +
            '<td class="text-right"><strong>' + t.transfer_qty + '</strong></td>' +
          '</tr>';
        });

        html += '</tbody></table></div></div>';
      } else {
        html += '<div class="vsb-empty-state"><i class="fa fa-check-circle"></i><p>No transfer candidates found — inventory is well-distributed.</p></div>';
      }

      $("#vsbCrossBranchIntelContent").html(html).show();
    });
  }

  // ═══════════════════════════════════════════
  // Sales Patterns Board (Phase 4)
  // ═══════════════════════════════════════════
  function loadSalesPatterns(days) {
    showLoading("SalesPatterns");

    fetchAnalytics("sales-pattern-analysis", {
      days: days
    }, function (err, data) {
      hideLoading("SalesPatterns");

      if (err || !data) {
        showError("SalesPatterns", err || "No data returned");
        return;
      }

      loadedBoards["sales-patterns"] = true;

      var dow = data.day_of_week || [];
      var trends = data.category_trends || [];
      var branches = data.branch_performance || [];
      var insights = data.insights || [];
      var html = "";

      // AI Insights Cards
      if (insights.length > 0) {
        html += '<div class="row" style="margin-bottom:16px;">';
        insights.forEach(function (ins) {
          html += '<div class="col-md-6"><div class="vsb-pattern-insight">' +
            '<div class="vsb-pattern-insight-icon"><i class="fa ' + (ins.icon || 'fa-lightbulb-o') + '"></i></div>' +
            '<div class="vsb-pattern-insight-text">' + esc(ins.text) + '</div>' +
          '</div></div>';
        });
        html += '</div>';
      }

      // Day of Week chart (bar representation in HTML)
      if (dow.length > 0) {
        html += '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-calendar"></i> Sales by Day of Week</div>' +
          '<div class="vsb-dow-chart">';

        dow.forEach(function (d) {
          var pct = d.pct_of_peak || 0;
          var barColor = pct > 80 ? '#10B981' : (pct > 50 ? '#F59E0B' : '#EF4444');
          html += '<div class="vsb-dow-bar-wrap">' +
            '<div class="vsb-dow-label">' + (d.day || '?').substring(0, 3) + '</div>' +
            '<div class="vsb-dow-bar-outer">' +
              '<div class="vsb-dow-bar-fill" style="height:' + pct + '%; background:' + barColor + ';" title="' + formatCurrency(d.revenue) + '"></div>' +
            '</div>' +
            '<div class="vsb-dow-val">' + formatCurrency(d.revenue) + '</div>' +
            '<div class="vsb-dow-meta">' + d.bills + ' bills · ' + d.items + ' items</div>' +
          '</div>';
        });

        html += '</div></div>';
      }

      // Category Trends Table
      if (trends.length > 0) {
        html += '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-line-chart"></i> Category Momentum (' + trends.length + ')</div>' +
          '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
            '<th>Category</th><th>Trend</th>' +
            '<th class="text-right">Recent (' + (days / 2) + 'd)</th>' +
            '<th class="text-right">Prior (' + (days / 2) + 'd)</th>' +
            '<th class="text-right">Change</th>' +
          '</tr></thead><tbody>';

        trends.forEach(function (t) {
          var badge = '';
          if (t.trend === 'rising') {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-fast"><i class="fa fa-arrow-up"></i> Rising</span>';
          } else if (t.trend === 'falling') {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-nm"><i class="fa fa-arrow-down"></i> Falling</span>';
          } else {
            badge = '<span class="vsb-fns-badge vsb-fns-badge-slow"><i class="fa fa-minus"></i> Stable</span>';
          }
          var changeClass = t.change_pct > 0 ? 'text-success' : (t.change_pct < 0 ? 'text-danger' : '');
          var changeSign = t.change_pct > 0 ? '+' : '';

          html += '<tr>' +
            '<td><strong>' + esc(t.category) + '</strong></td>' +
            '<td>' + badge + '</td>' +
            '<td class="text-right">' + formatCurrency(t.recent_revenue) + ' <small>(' + t.recent_count + ')</small></td>' +
            '<td class="text-right">' + formatCurrency(t.prior_revenue) + ' <small>(' + t.prior_count + ')</small></td>' +
            '<td class="text-right ' + changeClass + '"><strong>' + changeSign + t.change_pct + '%</strong></td>' +
          '</tr>';
        });

        html += '</tbody></table></div></div>';
      }

      // Branch Performance Table
      if (branches.length > 0) {
        html += '<div class="vsb-table-card">' +
          '<div class="vsb-table-title"><i class="fa fa-building"></i> Branch Performance Deviation</div>' +
          '<div class="table-responsive"><table class="table vsb-table"><thead><tr>' +
            '<th>Branch</th><th class="text-right">Bills</th>' +
            '<th class="text-right">Items</th><th class="text-right">Revenue</th>' +
            '<th class="text-right">Avg Ticket</th><th>Performance</th>' +
          '</tr></thead><tbody>';

        branches.forEach(function (b) {
          var perfBadge = '';
          if (b.performance === 'above') {
            perfBadge = '<span class="vsb-fns-badge vsb-fns-badge-fast"><i class="fa fa-arrow-up"></i> +' + b.deviation_pct + '%</span>';
          } else if (b.performance === 'below') {
            perfBadge = '<span class="vsb-fns-badge vsb-fns-badge-nm"><i class="fa fa-arrow-down"></i> ' + b.deviation_pct + '%</span>';
          } else {
            perfBadge = '<span class="vsb-fns-badge vsb-fns-badge-slow"><i class="fa fa-minus"></i> Avg</span>';
          }

          html += '<tr>' +
            '<td><strong>' + esc(b.branch_name) + '</strong></td>' +
            '<td class="text-right">' + b.bills + '</td>' +
            '<td class="text-right">' + b.items + '</td>' +
            '<td class="text-right">' + formatCurrency(b.revenue) + '</td>' +
            '<td class="text-right">' + formatCurrency(b.avg_ticket) + '</td>' +
            '<td>' + perfBadge + '</td>' +
          '</tr>';
        });

        html += '</tbody></table></div></div>';
      }

      $("#vsbSalesPatternsContent").html(html).show();
    });
  }

  // Load AI suggestions board on page init
  loadBoard("ai-suggestions");

});

