<?php
/**
 * Common PO Rate Override — capability page (CPO-19)
 *
 * This entry exists so the capability can be granted in Settings > Profile Access.
 * It has to be an active menu row to appear in that grid, which also places it in the
 * navigation of whoever holds it — so it needs a page to land on. There is nothing to
 * operate here; it explains the capability and names who currently holds it.
 */
?>
<style>
    .cpo-cap .box-body        { font-size: 13px; }
    .cpo-cap .lead-note       { font-size: 14px; margin-bottom: 18px; }
    .cpo-cap table            { font-size: 13px; }
    .cpo-cap .muted           { color: #999; }
    .cpo-cap dt               { margin-top: 10px; }
    .cpo-cap .state           { padding: 10px 14px; border-radius: 3px; margin-bottom: 18px; }
    .cpo-cap .state.have      { background: #dff0d8; border: 1px solid #c3e0b4; }
    .cpo-cap .state.have-not  { background: #f7f7f5; border: 1px solid #e2e2e0; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Purchase <small>Common PO Rate Override</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Purchase</a></li>
      <li class="active">Common PO Rate Override</li>
    </ol>
  </section>

  <section class="content cpo-cap">
    <div class="box box-primary">

      <div class="box-header with-border">
        <h3 class="box-title">What this permission does</h3>
      </div>

      <div class="box-body">

        <?php if (!empty($has_override)) { ?>
          <div class="state have">
            <i class="fa fa-unlock"></i>
            <b>You hold this permission.</b>
            You can change the rate on a supplier bill recalled from a Common PO.
          </div>
        <?php } else { ?>
          <div class="state have-not">
            <i class="fa fa-lock"></i>
            <b>You do not hold this permission.</b>
            On a supplier bill recalled from a Common PO the rate is locked to the
            agreed rate, and a bill saved with a different rate will be rejected.
          </div>
        <?php } ?>

        <p class="lead-note">
          The point of a Common PO is that the rate is agreed in advance. The supplier
          bill therefore takes its rate from the purchase order and does not allow it
          to be edited. This permission is the exception: it lets a named role change
          that rate when the agreement genuinely changed.
        </p>

        <dl>
          <dt>Where it applies</dt>
          <dd>Purchase &rarr; Supplier Bill Entry, on a bill recalled against a Common
              PO with a <b>Fixed</b> rate. An <b>Unfixed</b> PO carries no agreed rate,
              so there is nothing to override and this permission does not apply.</dd>

          <dt>What happens without it</dt>
          <dd>The rate field is read only, and the server rejects the save if the rate
              still departs from the purchase order. Both checks are applied &mdash;
              read only is a display convention, so the value is verified again on save.</dd>

          <dt>How to grant it</dt>
          <dd>Settings &rarr; Profile Access &rarr; choose the profile &rarr; Purchase
              Module &rarr; tick <b>Add</b> or <b>Edit</b> against
              <b>Common PO Rate Override</b>. The default is restricted: a profile with
              no tick cannot override.</dd>
        </dl>

        <h4 style="margin-top:24px;">Profiles that currently hold it</h4>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" style="max-width:520px;">
            <thead>
              <tr>
                <th>Profile</th>
                <th style="width:90px;">Add</th>
                <th style="width:90px;">Edit</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($holders)) { ?>
              <tr>
                <td colspan="3" class="muted">
                  No profile holds this permission. Nobody can override an agreed
                  Common PO rate.
                </td>
              </tr>
            <?php } else { ?>
              <?php foreach ($holders as $h) { ?>
              <tr>
                <td><?php echo html_escape($h['profile_name']); ?></td>
                <td><?php echo (!empty($h['add'])  ? '<i class="fa fa-check text-green"></i>' : '<span class="muted">&mdash;</span>'); ?></td>
                <td><?php echo (!empty($h['edit']) ? '<i class="fa fa-check text-green"></i>' : '<span class="muted">&mdash;</span>'); ?></td>
              </tr>
              <?php } ?>
            <?php } ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </section>
</div>
