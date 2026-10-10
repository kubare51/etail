/**
 * Opening Master - Cash Opening Balance
 * Standard list/form pattern matching smith_cmpy_op_bal
 */

$(document).ready(function () {

    var ctrl_page = window.location.pathname.split('/');
    // Find the relevant segments after admin_opening_master
    var page_segment = '';
    var action_segment = '';
    for (var i = 0; i < ctrl_page.length; i++) {
        if (ctrl_page[i] === 'cash_opening') {
            page_segment = 'cash_opening';
            action_segment = ctrl_page[i + 1] || '';
            break;
        }
    }

    if (page_segment === 'cash_opening') {
        switch (action_segment) {

            case 'list':
                get_cash_opening_list();
                break;

            case 'add':
            case 'edit':
                // Init datepicker on form
                $('#cash_opening_date').datepicker({
                    format: "dd-mm-yyyy",
                    autoclose: true,
                    todayHighlight: true,
                    endDate: "+0d"
                });

                // Init Select2 on branch dropdown
                $('#cash_branch_select').select2({
                    placeholder: "-- Select Branch --",
                    allowClear: true
                });
                break;
        }
    }
});

/**
 * Load cash opening list into DataTable
 */
function get_cash_opening_list() {

    $("div.overlay").css("display", "block");

    $.ajax({
        type: "POST",
        url: base_url + "index.php/admin_opening_master/cash_opening/ajax",
        dataType: "JSON",
        success: function (data) {

            var list = data.list;
            var oTable = $('#cash_opening_list').DataTable();
            oTable.clear().draw();

            if (list != null && list.length > 0) {

                oTable = $('#cash_opening_list').dataTable({
                    "bDestroy": true,
                    "bInfo": true,
                    "bFilter": true,
                    "bSort": true,
                    "ordering": false,
                    "dom": "lBfrtip",
                    "columnDefs": [
                        {
                            targets: [1, 2, 4],
                            className: "dt-body-left"
                        },
                        {
                            targets: [3],
                            className: "dt-body-right"
                        }
                    ],
                    "buttons": [
                        {
                            extend: "print",
                            footer: true,
                            title: "",
                            messageTop: "<div style='text-align:center;'><b>Cash Opening Balance</b></div>",
                            customize: function (win) {
                                $(win.document.body).find("table")
                                    .addClass("compact")
                                    .css("font-size", "10px");
                            }
                        },
                        {
                            extend: "excel",
                            footer: true,
                            title: "Cash Opening Balance"
                        }
                    ],
                    "aaData": list,
                    "aoColumns": [
                        {
                            "mDataProp": function (row, type, val, meta) {
                                return meta.row + 1;
                            }
                        },
                        { "mDataProp": "ref_id" },
                        { "mDataProp": "branch_name" },
                        {
                            "mDataProp": function (row) {
                                if (row.opening_date) {
                                    var parts = row.opening_date.split("-");
                                    if (parts.length === 3 && parts[0].length === 4) {
                                        return parts[2] + "-" + parts[1] + "-" + parts[0];
                                    }
                                }
                                return row.opening_date;
                            }
                        },
                        {
                            "mDataProp": function (row) {
                                return parseFloat(row.amount).toFixed(2);
                            }
                        },
                        {
                            "mDataProp": function (row) {
                                return row.remarks || "-";
                            }
                        },
                        {
                            "mDataProp": function (row) {
                                var editBtn = '<a href="' + base_url + 'index.php/admin_opening_master/cash_opening/edit/' + row.id + '" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-edit"></i></a>';
                                var deleteBtn = ' <button type="button" class="btn btn-xs btn-danger btn_delete_cash_opening" data-id="' + row.id + '" title="Delete"><i class="fa fa-trash"></i></button>';
                                return editBtn + deleteBtn;
                            }
                        }
                    ]
                });

            }

            $("div.overlay").css("display", "none");
        },
        error: function () {
            $("div.overlay").css("display", "none");
            $.toaster({ priority: "danger", title: "Error", message: "Failed to load cash opening records." });
        }
    });
}

// ==================== Form Submit ====================

$(document).on("click", "#cash_opening_submit", function () {

    var form_data = $("#cash_opening_form").serialize();

    // Detect edit mode
    var edit_id = $("#cash_edit_id").val();
    var save_url = base_url + "index.php/admin_opening_master/cash_opening/save";
    if (edit_id && edit_id !== "") {
        save_url = base_url + "index.php/admin_opening_master/cash_opening/update/" + edit_id;
    }

    // Client-side validation
    var branch = $("#cash_branch_select").val();
    if (!branch || branch === "") {
        $.toaster({ priority: "danger", title: "Validation", message: "Please select a branch." });
        return;
    }

    var my_Date = new Date();

    $("div.overlay").css("display", "block");

    $.ajax({
        data: form_data,
        url: save_url + "?nocache=" + my_Date.getUTCSeconds(),
        type: "POST",
        dataType: "JSON",
        success: function (response) {
            $("div.overlay").css("display", "none");
            if (response.status) {
                $.toaster({ priority: "success", title: "Success", message: response.msg });
                // Redirect to list after save
                setTimeout(function () {
                    window.location.href = base_url + "index.php/admin_opening_master/cash_opening/list";
                }, 1000);
            } else {
                $.toaster({ priority: "danger", title: "Error", message: response.msg });
            }
        },
        error: function () {
            $("div.overlay").css("display", "none");
            $.toaster({ priority: "danger", title: "Error", message: "Failed to save. Please try again." });
        }
    });
});

// ==================== Delete ====================

$(document).on("click", ".btn_delete_cash_opening", function () {
    var id = $(this).data("id");
    if (confirm("Are you sure you want to delete this cash opening?")) {
        $.ajax({
            url: base_url + "index.php/admin_opening_master/cash_opening/delete/" + id,
            type: "POST",
            dataType: "JSON",
            success: function (response) {
                if (response.status) {
                    $.toaster({ priority: "success", title: "Success", message: response.msg });
                    get_cash_opening_list();
                } else {
                    $.toaster({ priority: "danger", title: "Error", message: response.msg });
                }
            },
            error: function () {
                $.toaster({ priority: "danger", title: "Error", message: "Failed to delete." });
            }
        });
    }
});
