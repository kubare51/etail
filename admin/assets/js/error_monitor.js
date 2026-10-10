/**
 * Error Monitor — Client-Side Error Capture
 * 
 * Captures JS runtime errors, unhandled promise rejections, and AJAX failures.
 * Sends them to the server via /admin_settings/log_client_error endpoint.
 * 
 * Included in footer.php after jQuery is loaded.
 * Uses deduplication (same error won't be sent more than once per session).
 * Rate-limited to max 10 reports per page load to prevent flood.
 */
(function() {
    "use strict";

    var ERROR_ENDPOINT = (typeof base_url !== "undefined" ? base_url : "/admin/") + "error_monitor/log_client_error";
    var MAX_REPORTS = 10;
    var reportCount = 0;
    var reportedHashes = {};

    /**
     * Generate simple hash for deduplication.
     */
    function hashError(msg, file, line) {
        var str = (msg || "") + "|" + (file || "") + "|" + (line || "");
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return hash.toString(36);
    }

    /**
     * Send error report to server.
     */
    function reportError(data) {
        if (reportCount >= MAX_REPORTS) return;

        var hash = hashError(data.message, data.file, data.line);
        if (reportedHashes[hash]) return;
        reportedHashes[hash] = true;
        reportCount++;

        // Add page context
        data.url = window.location.href;
        data.user_agent = navigator.userAgent;
        data.timestamp = new Date().toISOString();

        // Use sendBeacon if available (works even during page unload)
        if (navigator.sendBeacon) {
            try {
                navigator.sendBeacon(ERROR_ENDPOINT, JSON.stringify(data));
                return;
            } catch (e) {
                // Fall through to AJAX
            }
        }

        // Fallback: jQuery AJAX (if available)
        if (typeof $ !== "undefined" && $.ajax) {
            $.ajax({
                url: ERROR_ENDPOINT,
                type: "POST",
                data: JSON.stringify(data),
                contentType: "application/json",
                timeout: 3000,
                // Intentionally no error handler — don't report errors about error reporting
            });
        }
    }

    // ── 1. Global JS Error Handler ──
    window.onerror = function(message, source, lineno, colno, error) {
        reportError({
            error_type: "js_error",
            message: message || "Unknown error",
            file: source || "",
            line: lineno || 0,
            column: colno || 0,
            stack_trace: error && error.stack ? error.stack : ""
        });

        // Don't suppress the error — let it appear in console
        return false;
    };

    // ── 2. Unhandled Promise Rejection ──
    window.addEventListener("unhandledrejection", function(event) {
        var message = "Unhandled Promise Rejection";
        var stack = "";

        if (event.reason) {
            if (typeof event.reason === "string") {
                message = event.reason;
            } else if (event.reason.message) {
                message = event.reason.message;
                stack = event.reason.stack || "";
            }
        }

        reportError({
            error_type: "js_error",
            message: "[Promise] " + message,
            file: "",
            line: 0,
            stack_trace: stack
        });
    });

    // ── 3. AJAX Error Interceptor ──
    // Hook into jQuery AJAX to catch server errors (500s, timeouts, etc.)
    if (typeof $ !== "undefined" && $.ajaxSetup) {
        $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
            // Skip cancelled requests and aborts
            if (jqXHR.statusText === "abort" || jqXHR.status === 0) return;

            // Skip error reporting endpoint itself (prevent loops)
            if (settings.url && settings.url.indexOf("log_client_error") !== -1) return;

            var message = "AJAX " + jqXHR.status + ": " + (thrownError || jqXHR.statusText);
            var responsePreview = "";

            try {
                responsePreview = (jqXHR.responseText || "").substring(0, 500);
            } catch (e) {
                responsePreview = "[Could not read response]";
            }

            reportError({
                error_type: "js_ajax",
                message: message,
                file: settings.url || "",
                line: 0,
                stack_trace: "Method: " + (settings.type || "GET") + "\nURL: " + (settings.url || "") 
                           + "\nStatus: " + jqXHR.status
                           + "\nResponse: " + responsePreview
            });
        });
    }

})();
