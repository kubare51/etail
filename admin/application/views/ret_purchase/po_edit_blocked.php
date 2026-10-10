<?php
/**
 * PO-EDIT-3: shown when a Purchase Order cannot be amended.
 *
 * A PO is only editable while it is still purely an intention. Once metal has been
 * received or a bill raised, the figures on those documents were struck against these
 * lines and rewriting them would leave the two disagreeing.
 *
 * The point of this page is to name the document that is in the way. "Cannot edit"
 * with no reason is what sends people to the phone.
 */
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Purchase <small>Edit Purchase Order</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Purchase</a></li>
      <li class="active">Edit Purchase Order</li>
    </ol>
  </section>

  <section class="content">
    <div class="box box-warning">

      <div class="box-header with-border">
        <h3 class="box-title">
          <?php echo html_escape($po_head['pur_no']); ?> cannot be edited
        </h3>
      </div>

      <div class="box-body">

        <table class="table table-condensed" style="max-width:520px;">
          <tr><td width="35%"><b>Purchase Order</b></td>
              <td><?php echo html_escape($po_head['pur_no']); ?></td></tr>
          <tr><td><b>Supplier</b></td>
              <td><?php echo html_escape($po_head['karigar_name']); ?></td></tr>
          <tr><td><b>PO Date</b></td>
              <td><?php echo html_escape($po_head['order_date_fmt']); ?></td></tr>
          <tr><td><b>Status</b></td>
              <td><?php echo html_escape($po_head['status_label']); ?></td></tr>
        </table>

        <div class="callout callout-warning" style="max-width:760px;">
          <h4><i class="fa fa-lock"></i> Why</h4>
          <ul style="margin:8px 0 0 -18px;">
            <?php foreach ((isset($blockers) ? $blockers : array()) as $b) { ?>
              <li style="margin-bottom:4px;"><?php echo html_escape($b); ?></li>
            <?php } ?>
          </ul>
        </div>

        <p class="help-block" style="max-width:760px;">
          Clear the reasons above in reverse order &mdash; bill first, then advance &mdash;
          and the Purchase Order becomes editable again. If it cannot be cleared, cancel
          this Purchase Order and raise a new one.
        </p>

        <a href="<?php echo base_url(); ?>index.php/admin_ret_purchase/purchase/pur_order"
           class="btn btn-default">Back to PO List</a>

        <?php if ((int)$po_head['is_common_po'] === 1) { ?>
          <a href="<?php echo base_url(); ?>index.php/admin_ret_purchase/common_po_trace/<?php echo (int)$po_head['id_customerorder']; ?>"
             class="btn btn-info" target="_blank">
            <i class="fa fa-sitemap"></i> See the full trace
          </a>
        <?php } ?>

      </div>
    </div>
  </section>
</div>
