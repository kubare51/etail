/**
 * ============================================================
 * LMX Master Module — Generic CRUD Handler
 * ============================================================
 * Consolidates 50k+ lines of master CRUD logic into a
 * reusable, configurable module for all simple masters.
 *
 * Usage:
 *   LMXMaster.init({entity: 'material', table_id: '#lmx_master_table', ...})
 *
 * @version 1.0.0
 * @created 2026-07-12
 * ============================================================
 */

var LMXMaster = (function() {
  'use strict';

  // Private state
  var config = {};
  var dataTable = null;
  var currentEditId = null;
  var pendingAjax = false;

  // ============================================================
  // PUBLIC API
  // ============================================================

  return {
    /**
     * Initialize master CRUD UI for a given entity.
     *
     * @param {Object} options
     *   - entity: 'material' (required)
     *   - table_id: '#lmx_master_table' (selector)
     *   - url_base: '/admin/material' (auto-derived if omitted)
     *   - columns: ['name', 'code', 'status', 'actions'] (optional)
     *   - before_save: function(data) (optional callback)
     *   - after_save: function(response) (optional callback)
     *   - after_delete: function() (optional callback)
     */
    init: function(options) {
      config = options || {};
      if (!config.entity) {
        console.error('[LMXMaster] entity is required');
        return false;
      }

      // Defaults
      config.url_base = config.url_base || ('/admin/' + config.entity);
      config.table_id = config.table_id || '#lmx_master_table';

      console.log('[LMXMaster] Initializing entity: ' + config.entity);

      // Set up event handlers
      this._bindEvents();

      // Initialize DataTable if table exists
      if ($(config.table_id).length) {
        this.initDataTable();
      }

      return true;
    },

    /**
     * Initialize or refresh DataTable for master list.
     */
    initDataTable: function() {
      if (dataTable) {
        dataTable.destroy();
      }

      console.log('[LMXMaster] Initializing DataTable for: ' + config.entity);

      var columns = config.columns || ['name', 'code', 'status', 'actions'];
      var columnDefs = columns.map(function(col) {
        return {
          data: col,
          render: function(data, type, row) {
            if (col === 'actions') {
              return LMXMaster._renderActions(row);
            }
            return data || '';
          }
        };
      });

      dataTable = $(config.table_id).DataTable({
        serverSide: false,
        dataSrc: 'responseData',
        columns: columnDefs,
        ajax: {
          url: config.url_base + '/list',
          type: 'GET'
        },
        columnDefs: [
          {
            targets: -1,  // Last column (actions)
            orderable: false,
            searchable: false
          }
        ],
        pageLength: 25,
        order: [[0, 'asc']],
        dom: 'lrtip',
        buttons: [],
        initComplete: function() {
          console.log('[LMXMaster] DataTable initialized');
        }
      });

      return dataTable;
    },

    /**
     * Refresh DataTable data from server.
     */
    refresh: function() {
      if (dataTable) {
        dataTable.ajax.reload(null, false);
      }
    },

    /**
     * Handle generic add form submission.
     *
     * @param {Object} options
     *   - form_id: '#form_add' (selector, required)
     *   - url: '/admin/material/save' (override url_base)
     */
    handleAdd: function(options) {
      options = options || {};
      var form_id = options.form_id || '#form_add';
      var submitUrl = options.url || (config.url_base + '/save');

      $(form_id).off('submit').on('submit', function(e) {
        e.preventDefault();

        if (pendingAjax) {
          console.log('[LMXMaster] Request in progress, skipping');
          return false;
        }

        var formData = $(this).serializeArray();
        var data = {};
        $.each(formData, function() {
          data[this.name] = this.value;
        });

        // Pre-save callback
        if (config.before_save && typeof config.before_save === 'function') {
          config.before_save(data);
        }

        pendingAjax = true;
        $.ajax({
          url: submitUrl,
          type: 'POST',
          data: data,
          dataType: 'json',
          success: function(response) {
            console.log('[LMXMaster] Add successful:', response);

            // Close modal
            var modalId = $(form_id).closest('.modal').attr('id');
            if (modalId) {
              $('#' + modalId).modal('hide');
            }

            // Post-save callback
            if (config.after_save && typeof config.after_save === 'function') {
              config.after_save(response);
            }

            // Refresh list
            LMXMaster.refresh();

            // Show success message
            if (response.message) {
              console.log(response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error('[LMXMaster] Add failed:', error);
            alert('Error saving record: ' + (xhr.responseJSON?.message || error));
          },
          complete: function() {
            pendingAjax = false;
          }
        });
      });
    },

    /**
     * Handle generic edit form submission.
     *
     * @param {Object} options
     *   - form_id: '#form_edit' (selector)
     *   - id_field: '#edit_id' (selector for hidden ID field)
     */
    handleEdit: function(options) {
      options = options || {};
      var form_id = options.form_id || '#form_edit';
      var id_field = options.id_field || '#edit_id';

      $(form_id).off('submit').on('submit', function(e) {
        e.preventDefault();

        if (pendingAjax) {
          console.log('[LMXMaster] Request in progress, skipping');
          return false;
        }

        var recordId = $(id_field).val();
        var submitUrl = config.url_base + '/update/' + recordId;

        var formData = $(this).serializeArray();
        var data = {};
        $.each(formData, function() {
          if (this.name !== 'id') {  // Exclude ID from form data
            data[this.name] = this.value;
          }
        });

        // Pre-save callback
        if (config.before_save && typeof config.before_save === 'function') {
          config.before_save(data);
        }

        pendingAjax = true;
        $.ajax({
          url: submitUrl,
          type: 'POST',
          data: data,
          dataType: 'json',
          success: function(response) {
            console.log('[LMXMaster] Update successful:', response);

            // Close modal
            var modalId = $(form_id).closest('.modal').attr('id');
            if (modalId) {
              $('#' + modalId).modal('hide');
            }

            // Post-save callback
            if (config.after_save && typeof config.after_save === 'function') {
              config.after_save(response);
            }

            // Refresh list
            LMXMaster.refresh();

            // Show success message
            if (response.message) {
              console.log(response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error('[LMXMaster] Update failed:', error);
            alert('Error updating record: ' + (xhr.responseJSON?.message || error));
          },
          complete: function() {
            pendingAjax = false;
          }
        });
      });
    },

    /**
     * Delete a record.
     *
     * @param {number} id
     * @return {boolean}
     */
    delete: function(id) {
      if (!confirm('Are you sure you want to delete this record?')) {
        return false;
      }

      if (pendingAjax) {
        console.log('[LMXMaster] Request in progress, skipping');
        return false;
      }

      var url = config.url_base + '/delete/' + id;

      // Pre-delete callback
      if (config.before_delete && typeof config.before_delete === 'function') {
        if (!config.before_delete(id)) {
          return false;
        }
      }

      pendingAjax = true;
      $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        success: function(response) {
          console.log('[LMXMaster] Delete successful:', response);

          // Post-delete callback
          if (config.after_delete && typeof config.after_delete === 'function') {
            config.after_delete(response);
          }

          // Refresh list
          LMXMaster.refresh();

          // Show success message
          if (response.message) {
            console.log(response.message);
          }
        },
        error: function(xhr, status, error) {
          console.error('[LMXMaster] Delete failed:', error);
          alert('Error deleting record: ' + (xhr.responseJSON?.message || error));
        },
        complete: function() {
          pendingAjax = false;
        }
      });

      return true;
    },

    /**
     * Toggle entity status (0 ↔ 1).
     *
     * @param {number} id
     * @param {number} currentStatus (0 or 1)
     * @return {boolean}
     */
    toggleStatus: function(id, currentStatus) {
      if (pendingAjax) {
        console.log('[LMXMaster] Request in progress, skipping');
        return false;
      }

      var newStatus = currentStatus ? 0 : 1;
      var url = config.url_base + '/update_status/' + id + '/' + newStatus;

      pendingAjax = true;
      $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        success: function(response) {
          console.log('[LMXMaster] Status updated:', response);
          LMXMaster.refresh();
        },
        error: function(xhr, status, error) {
          console.error('[LMXMaster] Status toggle failed:', error);
          alert('Error updating status: ' + error);
        },
        complete: function() {
          pendingAjax = false;
        }
      });

      return true;
    },

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    /**
     * Bind event listeners for standard CRUD actions.
     */
    _bindEvents: function() {
      var $table = $(config.table_id);

      // Edit button click
      $(document).off('click', '.btn-edit', LMXMaster._onEditClick);
      $(document).on('click', '.btn-edit', LMXMaster._onEditClick);

      // Delete button click
      $(document).off('click', '.btn-delete', LMXMaster._onDeleteClick);
      $(document).on('click', '.btn-delete', LMXMaster._onDeleteClick);

      // Status toggle
      $(document).off('click', '.btn-toggle-status', LMXMaster._onStatusToggle);
      $(document).on('click', '.btn-toggle-status', LMXMaster._onStatusToggle);

      console.log('[LMXMaster] Event handlers bound');
    },

    /**
     * Render action buttons for a row.
     */
    _renderActions: function(row) {
      var id = row.material_id || row.purity_id || row.stone_id || row.id;
      var statusCol = row.material_status || row.purity_status || row.stone_status || row.status;

      if (!id) {
        return '';
      }

      var statusClass = statusCol == 1 ? 'btn-warning' : 'btn-success';
      var statusText = statusCol == 1 ? 'Deactivate' : 'Activate';

      var html = '' +
        '<button class="btn btn-sm btn-info btn-edit" data-id="' + id + '">Edit</button> ' +
        '<button class="btn btn-sm btn-danger btn-delete" data-id="' + id + '">Delete</button> ' +
        '<button class="btn btn-sm ' + statusClass + ' btn-toggle-status" data-id="' + id + '" data-status="' + statusCol + '">' + statusText + '</button>';

      return html;
    },

    /**
     * Handle edit button click.
     */
    _onEditClick: function(e) {
      e.preventDefault();
      var id = $(this).attr('data-id');
      currentEditId = id;

      console.log('[LMXMaster] Edit clicked for ID: ' + id);

      // Open edit modal and load data
      var editUrl = config.url_base + '/edit/' + id;

      $.ajax({
        url: editUrl,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.data) {
            // Populate form fields
            $.each(response.data, function(key, value) {
              var $field = $('[name="' + key + '"]');
              if ($field.length) {
                if ($field.is(':checkbox') || $field.is(':radio')) {
                  $field.prop('checked', value == 1);
                } else {
                  $field.val(value);
                }
              }
            });

            // Set ID field
            $('#edit_id').val(id);

            // Show modal
            var modalId = '#edit_modal';
            if ($(modalId).length) {
              $(modalId).modal('show');
            }
          }
        },
        error: function(xhr, status, error) {
          console.error('[LMXMaster] Load edit form failed:', error);
        }
      });
    },

    /**
     * Handle delete button click.
     */
    _onDeleteClick: function(e) {
      e.preventDefault();
      var id = $(this).attr('data-id');
      LMXMaster.delete(id);
    },

    /**
     * Handle status toggle click.
     */
    _onStatusToggle: function(e) {
      e.preventDefault();
      var id = $(this).attr('data-id');
      var status = $(this).attr('data-status');
      LMXMaster.toggleStatus(id, status);
    }
  };
})();

// Auto-initialize if config present on page
$(document).ready(function() {
  if (typeof window.LMX_MASTER_CONFIG !== 'undefined') {
    LMXMaster.init(window.LMX_MASTER_CONFIG);
  }
});
