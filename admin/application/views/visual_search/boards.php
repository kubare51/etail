<!-- Content Wrapper. Contains page content -->
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/visual_search.css">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/visual_search_boards.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="content-wrapper">

  <!-- Compact Filter Bar -->
  <section class="content">

    <div class="vsb-filter-bar">
      <div class="vsb-filter-left">
        <label class="vsb-filter-label"><i class="fa fa-calendar"></i> Period</label>
        <select id="vsbDaysFilter" class="form-control vsb-days-select">
          <option value="30">0 – 30 days</option>
          <option value="90">31 – 90 days</option>
          <option value="120">91 – 120 days</option>
          <option value="180">121 – 180 days</option>
          <option value="240">181 – 240 days</option>
          <option value="365">241 – 365 days</option>
          <option value="9999">365+ days</option>
          <option value="custom">Custom Range</option>
        </select>

        <!-- Custom range inputs (shown when 'Custom' selected) -->
        <div id="vsbCustomRange" class="vsb-custom-range" style="display:none;">
          <input type="number" id="vsbCustomFrom" class="form-control vsb-range-input" min="0" max="9999" value="0" placeholder="From">
          <span class="vsb-range-sep">–</span>
          <input type="number" id="vsbCustomTo" class="form-control vsb-range-input" min="1" max="9999" value="365" placeholder="To">
          <span class="vsb-range-unit">days</span>
          <button class="btn btn-xs vsb-range-go" id="vsbCustomGoBtn" title="Apply custom range">
            <i class="fa fa-check"></i>
          </button>
        </div>

        <label class="vsb-filter-label" title="Actual Age = from tag creation date. Branch Age = from inward date to current branch.">
          <i class="fa fa-clock-o"></i> Age Type
          <i class="fa fa-info-circle" style="font-size:11px; color:#94a3b8; cursor:help;"
             title="Actual Age: Days since tag creation (old_tag_date). Branch Age: Days since item arrived at current branch (inward date)."></i>
        </label>
        <select id="vsbAgeTypeFilter" class="form-control vsb-days-select">
          <option value="1" selected>Actual Age</option>
          <option value="2">Branch Age</option>
        </select>

        <!-- Active filter chips (shown when filters applied) -->
        <div id="vsbActiveFilters" class="vsb-active-filters" style="display:none;"></div>
      </div>
      <div class="vsb-filter-right">
        <button class="btn vsb-filter-modal-btn" id="vsbFilterModalBtn" title="Advanced Filters">
          <i class="fa fa-sliders"></i> Filters
          <span class="vsb-filter-count" id="vsbFilterCount" style="display:none;">0</span>
        </button>
        <a href="<?php echo base_url(); ?>index.php/admin_visual_search"
           class="btn vsb-imgsearch-btn" title="Image Search">
          <i class="fa fa-search"></i> Image Search
        </a>
        <button class="btn vsb-refresh-btn" id="vsbRefreshBtn">
          <i class="fa fa-refresh"></i> Refresh
        </button>
      </div>
    </div>

    <!-- Context Banner (shown when arriving from search results) -->
    <div id="vsbContextBanner" class="vsb-context-banner" style="display:none;">
      <div class="vsb-context-inner">
        <i class="fa fa-magic vsb-context-icon"></i>
        <span class="vsb-context-text">
          Showing insights for: <strong id="vsbContextCategory"></strong>
          <span id="vsbContextCollection"></span>
        </span>
        <a href="<?php echo base_url(); ?>index.php/admin_visual_search/boards" class="btn btn-sm vsb-context-clear" title="Clear context and show all data">
          <i class="fa fa-times"></i> Show All
        </a>
      </div>
    </div>

    <!-- Slide-Out Filter Panel -->
    <div class="vsb-filter-overlay" id="vsbFilterOverlay"></div>
    <div class="vsb-filter-slider" id="vsbFilterSlider">
      <div class="vsb-slider-header">
        <h4><i class="fa fa-sliders"></i> Filters</h4>
        <button class="vsb-slider-close" id="vsbSliderClose">&times;</button>
      </div>

      <div class="vsb-slider-body">

        <!-- Two-column layout: tabs left, options right -->
        <div class="vsb-filter-tabs-wrapper">

          <!-- Left: Filter tab labels -->
          <div class="vsb-filter-tab-labels">
            <div class="vsb-filter-tab-label active" data-filter-tab="branch">
              <i class="fa fa-building"></i>
              <span>Branch</span>
              <span class="vsb-tab-badge" id="vsbBranchBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="category">
              <i class="fa fa-th-large"></i>
              <span>Category</span>
              <span class="vsb-tab-badge" id="vsbCategoryBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="product">
              <i class="fa fa-cube"></i>
              <span>Product</span>
              <span class="vsb-tab-badge" id="vsbProductBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="design">
              <i class="fa fa-diamond"></i>
              <span>Design</span>
              <span class="vsb-tab-badge" id="vsbDesignBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="subdesign">
              <i class="fa fa-bookmark-o"></i>
              <span>Sub-Design</span>
              <span class="vsb-tab-badge" id="vsbSubDesignBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="karigar">
              <i class="fa fa-user"></i>
              <span>Karigar</span>
              <span class="vsb-tab-badge" id="vsbKarigarBadge"></span>
            </div>
            <div class="vsb-filter-tab-label" data-filter-tab="weight">
              <i class="fa fa-balance-scale"></i>
              <span>Weight</span>
            </div>
          </div>

          <!-- Right: Filter options panel -->
          <div class="vsb-filter-tab-panels">

            <!-- Branch -->
            <div class="vsb-filter-tab-panel active" id="vsbTabBranch" data-panel="branch">
              <div class="vsb-panel-title">Branch</div>
              <input type="text" class="vsb-slider-search" id="vsbBranchSearch" placeholder="Search branches...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="branch" value="" class="vsb-check-all" checked> <span>All Branches</span>
              </label>
              <div class="vsb-slider-list" id="vsbBranchList"></div>
            </div>

            <!-- Category -->
            <div class="vsb-filter-tab-panel" id="vsbTabCategory" data-panel="category">
              <div class="vsb-panel-title">Category</div>
              <input type="text" class="vsb-slider-search" id="vsbCategorySearch" placeholder="Search categories...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="category" value="" class="vsb-check-all" checked> <span>All Categories</span>
              </label>
              <div class="vsb-slider-list" id="vsbCategoryList"></div>
            </div>

            <!-- Product -->
            <div class="vsb-filter-tab-panel" id="vsbTabProduct" data-panel="product">
              <div class="vsb-panel-title">Product</div>
              <input type="text" class="vsb-slider-search" id="vsbProductSearch" placeholder="Search products...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="product" value="" class="vsb-check-all" checked> <span>All Products</span>
              </label>
              <div class="vsb-slider-list" id="vsbProductList"></div>
            </div>

            <!-- Design -->
            <div class="vsb-filter-tab-panel" id="vsbTabDesign" data-panel="design">
              <div class="vsb-panel-title">Design</div>
              <input type="text" class="vsb-slider-search" id="vsbDesignSearch" placeholder="Search designs...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="design" value="" class="vsb-check-all" checked> <span>All Designs</span>
              </label>
              <div class="vsb-slider-list" id="vsbDesignList"></div>
            </div>

            <!-- Sub-Design -->
            <div class="vsb-filter-tab-panel" id="vsbTabSubDesign" data-panel="subdesign">
              <div class="vsb-panel-title">Sub-Design</div>
              <input type="text" class="vsb-slider-search" id="vsbSubDesignSearch" placeholder="Search sub-designs...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="subdesign" value="" class="vsb-check-all" checked> <span>All Sub-Designs</span>
              </label>
              <div class="vsb-slider-list" id="vsbSubDesignList"></div>
            </div>

            <!-- Karigar -->
            <div class="vsb-filter-tab-panel" id="vsbTabKarigar" data-panel="karigar">
              <div class="vsb-panel-title">Karigar</div>
              <input type="text" class="vsb-slider-search" id="vsbKarigarSearch" placeholder="Search karigars...">
              <label class="vsb-slider-item vsb-slider-item-all">
                <input type="checkbox" name="karigar" value="" class="vsb-check-all" checked> <span>All Karigars</span>
              </label>
              <div class="vsb-slider-list" id="vsbKarigarList"></div>
            </div>

            <!-- Weight Range -->
            <div class="vsb-filter-tab-panel" id="vsbTabWeight" data-panel="weight">
              <div class="vsb-panel-title">Weight Range (g)</div>
              <div class="vsb-weight-range">
                <input type="number" id="vsbWeightFrom" class="form-control vsb-range-input"
                       min="0" step="0.1" value="" placeholder="Min">
                <span class="vsb-range-sep">–</span>
                <input type="number" id="vsbWeightTo" class="form-control vsb-range-input"
                       min="0" step="0.1" value="" placeholder="Max">
              </div>
            </div>

          </div>
        </div>

      </div>

      <div class="vsb-slider-footer">
        <button class="btn vsb-filter-clear-btn" id="vsbFilterClearBtn">
          <i class="fa fa-eraser"></i> Clear All
        </button>
        <button class="btn vsb-filter-apply-btn" id="vsbFilterApplyBtn">
          <i class="fa fa-check"></i> Apply Filters
        </button>
      </div>
    </div>

    <!-- Dashboard Strip: Health Gauge + KPIs + Alert (single row) -->
    <div id="vsbDashboardStrip" class="vsb-dashboard-strip" style="display:none;">
      <div class="vsb-strip-gauge" id="vsbStripGauge"></div>
      <div class="vsb-strip-kpis">
        <div class="vsb-strip-kpi" id="vsbStripKpi1">
          <div class="vsb-strip-kpi-val" id="vsbStripTotalVal">—</div>
          <div class="vsb-strip-kpi-label">Total Inventory</div>
        </div>
        <div class="vsb-strip-kpi-divider"></div>
        <div class="vsb-strip-kpi" id="vsbStripKpi2">
          <div class="vsb-strip-kpi-val vsb-strip-kpi-danger" id="vsbStripDeadVal">—</div>
          <div class="vsb-strip-kpi-label">Aged Stock (&gt;90d)</div>
        </div>
        <div class="vsb-strip-kpi-divider"></div>
        <div class="vsb-strip-kpi" id="vsbStripKpi3">
          <div class="vsb-strip-kpi-val" id="vsbStripDeadPctVal">—</div>
          <div class="vsb-strip-kpi-label">Aged Stock %</div>
          <div class="vsb-strip-kpi-minibar"><div class="vsb-strip-kpi-minibar-fill" id="vsbStripDeadPctBar"></div></div>
        </div>
        <div class="vsb-strip-kpi-divider"></div>
        <div class="vsb-strip-kpi" id="vsbStripKpi4">
          <div class="vsb-strip-kpi-val" id="vsbStripFreshAgedVal">—</div>
          <div class="vsb-strip-kpi-label">Fresh : Aged</div>
        </div>
      </div>
      <div class="vsb-strip-alert" id="vsbStripAlert" style="display:none;" title="">
        <i class="fa fa-exclamation-triangle"></i>
        <span id="vsbStripAlertText">Alert</span>
      </div>
    </div>

    <!-- Tab Bar (underline style) -->
    <div class="vsb-tabbar">
      <div class="vsb-tabbar-scroll">
        <button class="vsb-utab vsb-utab-ai active" data-board="ai-suggestions">
          <i class="fa fa-magic"></i> AI Suggestions
        </button>
        <button class="vsb-utab" data-board="fast-movers">
          <i class="fa fa-rocket"></i> Fast Movers
        </button>
        <button class="vsb-utab" data-board="branch-rotation">
          <i class="fa fa-exchange"></i> Branch Rotation
        </button>
        <button class="vsb-utab" data-board="purchase-intel">
          <i class="fa fa-shopping-cart"></i> Purchase Intel
        </button>
        <button class="vsb-utab" data-board="fns-classification">
          <i class="fa fa-bar-chart"></i> FNS Classification
        </button>
        <button class="vsb-utab" data-board="stock-availability">
          <i class="fa fa-cubes"></i> Stock Availability
        </button>
        <button class="vsb-utab" data-board="design-performance">
          <i class="fa fa-diamond"></i> Design Performance
        </button>
        <button class="vsb-utab" data-board="age-analysis">
          <i class="fa fa-clock-o"></i> Age Analysis
        </button>
        <button class="vsb-utab" data-board="cross-branch-intel">
          <i class="fa fa-exchange"></i> Cross-Branch Intel
        </button>
        <button class="vsb-utab" data-board="sales-patterns">
          <i class="fa fa-line-chart"></i> Sales Patterns
        </button>
      </div>
    </div>

    <!-- Board Content Areas -->
    <div class="vsb-board-container">

      <!-- Fast Movers Board -->
      <div class="vsb-board" id="vsb-fast-movers" style="display:none;">
        <div class="vsb-board-loading" id="vsbFastMoversLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading collection velocity data...</p>
        </div>
        <div class="vsb-board-content" id="vsbFastMoversContent" style="display:none;"></div>
      </div>

      <!-- Branch Rotation Board -->
      <div class="vsb-board" id="vsb-branch-rotation" style="display:none;">
        <div class="vsb-board-loading" id="vsbBranchRotationLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading branch rotation data...</p>
        </div>
        <div class="vsb-board-content" id="vsbBranchRotationContent" style="display:none;"></div>
      </div>

      <!-- Purchase Intel Board -->
      <div class="vsb-board" id="vsb-purchase-intel" style="display:none;">
        <div class="vsb-board-loading" id="vsbPurchaseIntelLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading purchase intelligence...</p>
        </div>
        <div class="vsb-board-content" id="vsbPurchaseIntelContent" style="display:none;"></div>
      </div>

      <!-- FNS Classification Board -->
      <div class="vsb-board" id="vsb-fns-classification" style="display:none;">
        <div class="vsb-board-loading" id="vsbFNSClassificationLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Classifying stock as Fast / Non-moving / Slow...</p>
        </div>
        <div class="vsb-board-content" id="vsbFNSClassificationContent" style="display:none;"></div>
      </div>

      <!-- Stock Availability Board -->
      <div class="vsb-board" id="vsb-stock-availability" style="display:none;">
        <div class="vsb-board-loading" id="vsbStockAvailabilityLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading stock availability...</p>
        </div>
        <div class="vsb-board-content" id="vsbStockAvailabilityContent" style="display:none;">
          <div class="row vsb-stock-layout">
            <div class="col-md-5">
              <div class="vsb-map-card" id="vsbMapCard" style="display:none;">
                <div class="vsb-map-title">
                  <span><i class="fa fa-map-marker"></i> Branch Distribution — India</span>
                  <span style="font-size:11px; color:#94A3B8;">Hover to see details</span>
                </div>
                <div class="vsb-map-container vsb-map-portrait" id="vsbIndiaMap"></div>
                <div class="vsb-map-legend">
                  <span><span class="vsb-map-legend-dot" style="background:#10B981;"></span> High Stock</span>
                  <span><span class="vsb-map-legend-dot" style="background:#F59E0B;"></span> Medium Stock</span>
                  <span><span class="vsb-map-legend-dot" style="background:#EF4444;"></span> Low Stock</span>
                </div>
              </div>
            </div>
            <div class="col-md-7" id="vsbBranchCardsColumn">
            </div>
          </div>
        </div>
      </div>

      <!-- Design Performance Board -->
      <div class="vsb-board" id="vsb-design-performance" style="display:none;">
        <div class="vsb-board-loading" id="vsbDesignPerformanceLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading design performance...</p>
        </div>
        <div class="vsb-board-content" id="vsbDesignPerformanceContent" style="display:none;"></div>
      </div>

      <!-- Age Analysis Board -->
      <div class="vsb-board" id="vsb-age-analysis" style="display:none;">
        <div class="vsb-board-loading" id="vsbAgeAnalysisLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading age analysis...</p>
        </div>
        <div class="vsb-board-content" id="vsbAgeAnalysisContent" style="display:none;"></div>
      </div>

      <!-- AI Suggestions Board -->
      <div class="vsb-board" id="vsb-ai-suggestions">
        <div class="vsb-board-loading" id="vsbAISuggestionsLoading">
          <i class="fa fa-magic fa-spin"></i> Generating AI insights…
        </div>
        <div class="vsb-board-content" id="vsbAISuggestionsContent" style="display:none;"></div>

        <!-- Best Sellers vs Aged Stock Comparison Panel -->
        <div id="vsbComparisonPanel" class="vsb-comparison-panel" style="display:none;">
          <div class="row">
            <div class="col-md-6">
              <div class="vsb-compare-card vsb-compare-good">
                <div class="vsb-compare-header">
                  <i class="fa fa-trophy"></i> Top 5 Best Sellers
                </div>
                <div class="vsb-compare-body" id="vsbBestSellersList"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="vsb-compare-card vsb-compare-bad">
                <div class="vsb-compare-header">
                  <i class="fa fa-exclamation-triangle"></i> Top 5 Aged Stock
                </div>
                <div class="vsb-compare-body" id="vsbDeadStockList"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cross-Branch Intel Board -->
      <div class="vsb-board" id="vsb-cross-branch-intel" style="display:none;">
        <div class="vsb-board-loading" id="vsbCrossBranchIntelLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Analyzing cross-branch transfer opportunities...</p>
        </div>
        <div class="vsb-board-content" id="vsbCrossBranchIntelContent" style="display:none;"></div>
      </div>

      <!-- Sales Patterns Board -->
      <div class="vsb-board" id="vsb-sales-patterns" style="display:none;">
        <div class="vsb-board-loading" id="vsbSalesPatternsLoading">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Analyzing sales patterns...</p>
        </div>
        <div class="vsb-board-content" id="vsbSalesPatternsContent" style="display:none;"></div>
      </div>

    </div>

    <!-- Footer Action Bar -->
    <div id="vsbFooterBar" class="vsb-footer-bar" style="display:none;">
      <button class="btn vsb-footer-btn vsb-footer-btn-primary" id="vsbFooterAgeBtn">
        <i class="fa fa-clock-o"></i> Run Age Analysis
      </button>
      <button class="btn vsb-footer-btn vsb-footer-btn-amber" id="vsbFooterPromoBtn">
        <i class="fa fa-tags"></i> Suggest Promotions
      </button>
      <button class="btn vsb-footer-btn vsb-footer-btn-blue" id="vsbFooterNotifyBtn">
        <i class="fa fa-bell"></i> Notify Branches
      </button>
    </div>

  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  // Save Chart.js v4 before AdminLTE footer loads Chart.js v1 which overwrites window.Chart
  window.Chart4 = window.Chart;
</script>
<script defer src="<?php echo base_url(); ?>assets/js/visual_search_boards.js"></script>
