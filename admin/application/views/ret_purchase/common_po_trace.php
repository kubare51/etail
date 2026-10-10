<?php
/**
 * Common PO — Document Trace (CPO-17)
 * BRS section 7 / acceptance criterion 19:
 *   PO -> Advance Payment -> GRN -> Supplier Bill -> Vendor Ledger
 *
 * Cancelled and reversed documents are SHOWN and marked, never filtered out. A trace
 * that hides a cancelled bill cannot explain why an advance was released — which is
 * precisely the question this screen exists to answer.
 */
$head        = $trace['head'];
$lines       = $trace['lines'];
$advances    = $trace['advances'];
$bills       = $trace['bills'];
$adjustments = $trace['adjustments'];
$ledger      = $trace['ledger'];

$RATE_TYPE = array(0 => '-', 1 => 'Fixed', 2 => 'Unfixed', 3 => 'Mixed');
$STATUS    = array(0 => 'Open', 1 => 'Advance Paid', 2 => 'Received', 3 => 'Billed', 4 => 'Closed', 5 => 'Cancelled');
$BASIS     = array(0 => 'Pure Wt', 1 => 'Gross Wt', 2 => 'Net Wt');

/* money_format_india() (helpers/lmx/functions/common_helper.php, autoloaded) groups by
   the Indian lakh/crore convention — 12,29,820.00. number_format() would print
   1,229,820.00 here while the same figure on the PO list and the status report, which
   are rendered in JS through the footer's money_format_india(), showed the Indian
   grouping. Same number, two formats, depending on which language drew the screen. */
function cpo_money($v) { return money_format_india($v, 2); }
function cpo_wt($v)    { return money_format_india($v, 3); }

/**
 * The "!" marker beside a section heading. Hovering it explains what that section
 * shows and where its figures come from.
 *
 * Kept off the page and behind a marker on purpose: the trace is a table of documents
 * and paragraphs of prose between the tables made it unreadable.
 *
 * data-html is needed for the line breaks. The content is authored here, never user
 * data, but it is escaped anyway so an em-dash or quote cannot break the attribute.
 */
function cpo_info($title, $html)
{
    $tpl = '<div class="popover cpo-pop" role="tooltip"><div class="arrow"></div>'
         . '<h3 class="popover-title"></h3><div class="popover-content"></div></div>';

    return '<i class="info-mark" data-toggle="popover" data-trigger="hover focus"'
         . ' data-placement="right" data-html="true" data-container="body"'
         . ' data-template="' . htmlspecialchars($tpl, ENT_QUOTES) . '"'
         . ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"'
         . ' data-content="' . htmlspecialchars($html, ENT_QUOTES) . '">!</i>';
}

$variance = round((float)$head['received_wt'] - (float)$head['committed_wt'], 3);
?>
<style>
    .cpo-trace .box-title      { font-weight: 600; }
    .cpo-trace .stage          { border-left: 3px solid #d2d6de; padding-left: 12px; margin-bottom: 18px; }
    .cpo-trace .stage.done     { border-left-color: #00a65a; }
    .cpo-trace .stage.pending  { border-left-color: #d2d6de; }
    .cpo-trace .stage.void     { border-left-color: #dd4b39; }
    .cpo-trace .stage-no       { display:inline-block; width:22px; height:22px; line-height:22px;
                                 text-align:center; border-radius:50%; background:#3c8dbc; color:#fff;
                                 font-size:11px; margin-right:6px; }
    .cpo-trace table           { font-size: 12px; }
    .cpo-trace .num            { text-align: right; }
    .cpo-trace .void-row       { background: #fdf3f2; color: #a94442; }
    .cpo-trace .void-row td    { text-decoration: line-through; }
    .cpo-trace .void-row td.reason { text-decoration: none; font-style: italic; }
    .cpo-trace .summary td     { padding: 4px 8px; }

    /* Every figure here is derived from something else, and the screen is read by people
       settling a supplier account. The explanations live behind this marker rather than
       on the page, so the trace itself stays a table of documents. */
    .cpo-trace .info-mark      { display:inline-block; width:16px; height:16px; line-height:15px;
                                 text-align:center; border-radius:50%; border:1px solid #3c8dbc;
                                 color:#3c8dbc; background:#fff; font-size:11px; font-weight:700;
                                 font-style:normal; margin-left:6px; cursor:help;
                                 vertical-align:middle; font-family:Georgia,serif; }
    .cpo-trace .info-mark:hover { background:#3c8dbc; color:#fff; }
    .cpo-trace h4 .info-mark   { vertical-align:2px; }
    /* Column headings carry their own hover text; the cue only appears on hover so the
       header row stays clean at rest. */
    .cpo-trace th[title]       { cursor: help; }
    .cpo-trace th[title]:hover { border-bottom: 1px dotted #999; }
    .cpo-trace .summary b[title]       { cursor: help; }
    .cpo-trace .summary b[title]:hover { border-bottom: 1px dotted #999; }
    .cpo-trace .popover        { max-width: 380px; }
    .cpo-trace .popover-content{ font-size: 12px; line-height: 1.55; }
    .popover.cpo-pop           { max-width: 380px; }
    .popover.cpo-pop .popover-content { font-size: 12px; line-height: 1.55; }
    .popover.cpo-pop .formula  { font-family: Menlo, Consolas, monospace; font-size: 11px;
                                 background: #f4f4f2; padding: 1px 4px; border-radius: 2px;
                                 white-space: nowrap; }
</style>

<div class="content-wrapper">
  <section class="content cpo-trace">

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Common PO Trace &mdash; <?php echo html_escape($head['pur_no']); ?>
          <?php echo cpo_info('About this page',
            'Follows one Common PO through every document it produced, in order: '
          . '<b>Purchase Order &rarr; Advance Payment &rarr; GRN &rarr; Supplier Bill &rarr; Vendor Ledger</b>.'
          . '<br><br><b>Cancelled and reversed documents are shown, not hidden</b> &mdash; struck '
          . 'through and marked, so the history still reads. They carry no weight in any total.'
          . '<br><br><b>Committed</b> is what was agreed; <b>Received</b> is what arrived. A negative '
          . 'figure in brackets is a short delivery &mdash; the supplier still owes metal, and any '
          . 'advance covering it stays receivable.'
          . '<br><br>Amounts are rupees to 2 decimals; weights are grams to 3. Column headings '
          . 'explain themselves on hover.'); ?>
        </h3>
        <div class="box-tools pull-right">
          <a href="<?php echo base_url(); ?>index.php/admin_ret_purchase/purchase/pur_order" class="btn btn-default btn-sm">Back to PO List</a>
        </div>
      </div>

      <div class="box-body">

        <!-- ============ Header ============ -->
        <div class="row">
          <div class="col-md-6">
            <table class="table table-condensed summary">
              <tr><td width="40%"><b title="The karigar / supplier this PO was raised on. The advance and the bill both post to this account.">Supplier</b></td><td><?php echo html_escape($head['karigar_name']); ?></td></tr>
              <tr><td><b title="The date the rate was agreed — not the delivery date.">PO Date</b></td><td><?php echo ($head['order_date'] ? date('d-m-Y', strtotime($head['order_date'])) : ''); ?></td></tr>
              <tr><td><b title="Fixed = a rate was agreed up front. Unfixed = quantity only, rate settled at Supplier Bill Entry. Mixed = the lines disagree.">Rate Type</b></td><td><?php echo (isset($RATE_TYPE[$head['rate_type_fix']]) ? $RATE_TYPE[$head['rate_type_fix']] : '-'); ?></td></tr>
              <tr><td><b title="The user who created the purchase order.">Raised By</b></td><td><?php echo html_escape($head['created_by_name']); ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-condensed summary">
              <tr><td width="40%"><b title="How far along the flow this PO has travelled: Open, Advance Paid, Received, Billed, Closed or Cancelled.">Status</b></td>
                  <td><?php echo (isset($STATUS[$head['common_po_status']]) ? $STATUS[$head['common_po_status']] : ''); ?></td></tr>
              <tr><td><b title="MD approval. Until this says Yes the PO cannot take an advance and cannot be recalled onto a supplier bill.">Approved</b></td>
                  <td><?php echo ((int)$head['common_po_approved'] === 1
                        ? 'Yes' . ($head['common_po_approved_on'] ? ' &mdash; ' . date('d-m-Y', strtotime($head['common_po_approved_on'])) : '')
                        : '<span class="text-red">No</span>'); ?></td></tr>
              <tr><td><b title="What the supplier agreed to deliver: the pure (fine) weight and pieces totalled across the PO lines.">Committed</b></td><td><?php echo cpo_wt($head['committed_wt']); ?> g / <?php echo (int)$head['order_pcs']; ?> pcs</td></tr>
              <tr><td><b title="What actually arrived, written back from the supplier bill. The bracketed figure is the shortfall or excess against Committed.">Received</b></td>
                  <td><?php echo cpo_wt($head['received_wt']); ?> g / <?php echo (int)$head['received_pcs']; ?> pcs
                  <?php if (abs($variance) > 0.0005) { ?>
                      <span class="text-<?php echo ($variance < 0 ? 'red' : 'yellow'); ?>"
                            title="<?php echo ($variance < 0 ? 'Short delivery — less arrived than was committed.' : 'Excess — more arrived than was committed.'); ?>">
                        (<?php echo ($variance > 0 ? '+' : ''); ?><?php echo cpo_wt($variance); ?> g)
                      </span>
                  <?php } ?>
                  </td></tr>
            </table>
          </div>
        </div>

        <hr>

        <!-- ============ 1. PO ============ -->
        <div class="stage done">
          <h4><span class="stage-no">1</span> Purchase Order &mdash; <?php echo html_escape($head['pur_no']); ?>
            <?php echo cpo_info('The agreement',
              'One row per bullion line, exactly as saved and approved.'
            . '<br><br>Bullion is priced on <b>pure weight</b>, not gross:<br>'
            . '<span class="formula">Net = Gross &minus; Stone</span><br>'
            . '<span class="formula">Pure = Net &times; Purity &divide; 100</span><br>'
            . 'The value is struck on whichever weight the <b>Basis</b> column names.'
            . '<br><br>Tax comes from the product&rsquo;s tax group &mdash; it is not typed by hand.'
            . '<br><br>Nothing here changes after approval unless the user holds the '
            . 'Common PO Rate Override permission.'); ?>
          </h4>
          <div class="table-responsive">
            <table class="table table-bordered table-condensed">
              <thead><tr>
                <th title="Bullion category from the category master, e.g. BULLION GOLD.">Category</th>
                <th title="Product from the product master. Its tax group decides the tax rate on this line.">Product</th>
                <th title="Fineness of the metal, e.g. 99.5000. Drives the pure weight.">Purity</th>
                <th class="num" title="Number of pieces / bars committed.">Pcs</th>
                <th class="num" title="Gross weight in grams, as weighed.">Gross</th>
                <th class="num" title="Stone weight deducted. Normally zero for bullion.">Stone</th>
                <th class="num" title="Net = Gross minus Stone.">Net</th>
                <th class="num" title="Pure (fine) weight = Net x Purity / 100. This is what you actually pay for.">Pure</th>
                <th title="Which weight the rate is applied to: Pure, Gross or Net. Bullion is normally Pure.">Basis</th>
                <th title="Fixed = rate agreed now. Unfixed = rate settled later at Supplier Bill Entry.">Rate Type</th>
                <th class="num" title="Agreed rate per gram. Blank or zero on an Unfixed line — there is no agreed rate yet.">Rate/Gm</th>
                <th class="num" title="Value before tax = weight on the stated Basis x Rate per gram.">Taxable</th>
                <th class="num" title="Tax on this line, with the percentage taken from the product's tax group. Inclusive or Exclusive per the tax master.">Tax</th>
                <th class="num" title="Taxable plus Tax. Summed across the lines this is the PO Value in the ledger below.">Total</th>
              </tr></thead>
              <tbody>
              <?php foreach ($lines as $l) { ?>
                <tr>
                  <td><?php echo html_escape($l['category_name']); ?></td>
                  <td><?php echo html_escape($l['product_name']); ?></td>
                  <td><?php echo html_escape($l['purity_name']); ?></td>
                  <td class="num"><?php echo (int)$l['no_of_pcs']; ?></td>
                  <td class="num"><?php echo cpo_wt($l['gross_wt']); ?></td>
                  <td class="num"><?php echo cpo_wt($l['stone_wt']); ?></td>
                  <td class="num"><?php echo cpo_wt($l['net_wt']); ?></td>
                  <td class="num"><?php echo cpo_wt($l['pure_wt']); ?></td>
                  <td><?php echo (isset($BASIS[$l['rate_basis']]) ? $BASIS[$l['rate_basis']] : ''); ?></td>
                  <td><?php echo ((int)$l['rate_type_fix'] === 2 ? 'Unfixed' : 'Fixed'); ?></td>
                  <td class="num"><?php echo cpo_money($l['rate_per_gram']); ?></td>
                  <td class="num"><?php echo cpo_money($l['taxable_amount']); ?></td>
                  <td class="num"><?php echo cpo_money($l['tax_amount']); ?> <small>(<?php echo number_format($l['tax_percent'],2); ?>%)</small></td>
                  <td class="num"><b><?php echo cpo_money($l['total_amount']); ?></b></td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ============ 2. Advance ============ -->
        <div class="stage <?php echo (!empty($advances) ? 'done' : 'pending'); ?>">
          <h4><span class="stage-no">2</span> Advance Payment
            <?php echo cpo_info('Money paid before delivery',
              'Each advance starts fully unadjusted. When a supplier bill is raised against '
            . 'this PO the system sets the advance against it automatically and <b>Adjusted</b> rises.'
            . '<br><br><b>Unadjusted</b> is what is still sitting with the supplier &mdash; the amount '
            . 'at risk if nothing is delivered.'
            . '<br><br>An advance is capped at the PO value on a Fixed PO. An Unfixed PO has no '
            . 'ceiling, because it has no agreed value yet.'
            . '<br><br>A cancelled advance is struck through and counts for nothing.'); ?>
          </h4>
          <?php if (empty($advances)) { ?>
            <p class="text-muted">
              No advance has been paid against this Purchase Order. The supplier will be paid
              in full when the bill is raised.
            </p>
          <?php } else { ?>
            <div class="table-responsive">
              <table class="table table-bordered table-condensed">
                <thead><tr>
                  <th title="Payment reference number from Smith / Supplier Payment.">Reference</th>
                  <th title="Date the advance was paid.">Paid On</th>
                  <th title="The user who entered the payment.">By</th>
                  <th class="num" title="Advance paid. Capped at the PO value for a Fixed PO; an Unfixed PO has no ceiling because it has no agreed value.">Amount</th>
                  <th class="num" title="How much of this advance has been set against a supplier bill so far.">Adjusted</th>
                  <th class="num" title="Amount minus Adjusted — advance still held by the supplier against undelivered metal.">Unadjusted</th>
                  <th title="Live, or Cancelled. A cancelled advance is excluded from every total on this page.">Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($advances as $a) {
                    $void = ((int)$a['pay_status'] === 2); ?>
                  <tr class="<?php echo ($void ? 'void-row' : ''); ?>">
                    <td><?php echo html_escape($a['pay_refno']); ?></td>
                    <td><?php echo html_escape($a['paid_on']); ?></td>
                    <td><?php echo html_escape($a['created_by_name']); ?></td>
                    <td class="num"><?php echo cpo_money($a['pay_amt']); ?></td>
                    <td class="num"><?php echo cpo_money($a['advance_adjusted']); ?></td>
                    <td class="num"><?php echo cpo_money($a['unadjusted']); ?></td>
                    <td class="reason"><?php echo ($void ? 'Cancelled' : 'Live'); ?></td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } ?>
        </div>

        <!-- ============ 3 & 4. GRN + Supplier Bill ============ -->
        <div class="stage <?php echo (!empty($bills) ? 'done' : 'pending'); ?>">
          <h4><span class="stage-no">3</span> GRN &amp; <span class="stage-no">4</span> Supplier Bill
            <?php echo cpo_info('Metal arriving and being invoiced',
              'These two stages share a row because the GRN is joined to this PO <b>through the '
            . 'supplier bill</b> &mdash; the bill names both, so there is nothing to link on the GRN screen.'
            . '<br><br>A bill saved without a GRN shows <span style="color:#dd4b39">none</span>, and '
            . 'every amount on it will be zero.'
            . '<br><br><span class="formula">Balance = Bill Amount &minus; Advance Adjusted</span><br>'
            . 'Zero means the advance covered the bill exactly.'
            . '<br><br>Cancelling a bill releases its adjusted advance back to unadjusted.'); ?>
          </h4>
          <?php if (empty($bills)) { ?>
            <p class="text-muted">
              No supplier bill has been raised against this Purchase Order. Any advance above
              is still fully receivable.
            </p>
          <?php } else { ?>
            <div class="table-responsive">
              <table class="table table-bordered table-condensed">
                <thead><tr>
                  <th title="Goods Receipt Note the metal arrived on. 'none' means the bill was saved without a GRN, which zeroes every amount on it.">GRN</th>
                  <th title="Date the metal was received.">GRN Date</th>
                  <th class="num" title="Weight recorded on the GRN.">GRN Wt</th>
                  <th title="Supplier bill reference raised against this PO.">Bill</th>
                  <th title="Date of the supplier bill.">Bill Date</th>
                  <th class="num" title="Weight billed. Compare with GRN Wt — they should agree.">Bill Wt</th>
                  <th class="num" title="Total value of the supplier bill, including tax.">Bill Amount</th>
                  <th class="num" title="How much advance the system set against this bill automatically when it was saved.">Advance Adjusted</th>
                  <th class="num" title="Bill Amount minus Advance Adjusted — what is still to be paid on this bill.">Balance</th>
                  <th title="Live, or Cancelled with the reason. Cancelling a bill releases its adjusted advance back to unadjusted.">Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($bills as $b) {
                    $void = ((int)$b['bill_status'] === 2);
                    $bal  = round((float)$b['bill_amount'] - (float)$b['advance_adjusted_amt'], 2); ?>
                  <tr class="<?php echo ($void ? 'void-row' : ''); ?>">
                    <td><?php echo ($b['grn_ref_no'] !== '' ? html_escape($b['grn_ref_no']) : '<span class="text-red">none</span>'); ?></td>
                    <td><?php echo html_escape($b['grn_date']); ?></td>
                    <td class="num"><?php echo cpo_wt($b['grn_wt']); ?></td>
                    <td><?php echo html_escape($b['po_ref_no']); ?></td>
                    <td><?php echo html_escape($b['bill_date']); ?></td>
                    <td class="num"><?php echo cpo_wt($b['bill_wt']); ?></td>
                    <td class="num"><?php echo cpo_money($b['bill_amount']); ?></td>
                    <td class="num"><?php echo cpo_money($b['advance_adjusted_amt']); ?></td>
                    <td class="num"><b><?php echo cpo_money($bal); ?></b></td>
                    <td class="reason">
                      <?php echo ($void ? 'Cancelled' : 'Live'); ?>
                      <?php if ($void && $b['cancel_reason']) { ?><br><small><?php echo html_escape($b['cancel_reason']); ?></small><?php } ?>
                    </td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } ?>
        </div>

        <!-- ============ Adjustments ============ -->
        <?php if (!empty($adjustments)) { ?>
        <div class="stage done">
          <h4><span class="stage-no">&#8644;</span> Advance Adjustments
            <?php echo cpo_info('Where stage 2 meets stage 4',
              'One row per advance set against one bill. Written by the system when a bill is '
            . 'saved &mdash; never keyed by hand.'
            . '<br><br>An adjustment is a <b>link between two existing entries</b>, so it posts '
            . 'nothing of its own to the supplier ledger: the advance is already a debit there and '
            . 'the bill is already a credit. Posting it again would count the advance twice.'
            . '<br><br>Cancelling a bill reverses its adjustment; the advance becomes unadjusted '
            . 'again and is available for the next bill.'); ?>
          </h4>
          <div class="table-responsive">
            <table class="table table-bordered table-condensed">
              <thead><tr>
                <th title="The advance payment reference being set against a bill.">Advance</th>
                <th title="The supplier bill the advance was applied to.">Bill</th>
                <th class="num" title="Amount of advance applied in this adjustment.">Adjusted</th>
                <th title="When the adjustment was written — that is, when the bill was saved.">On</th>
                <th title="Live, or Reversed. A reversed adjustment returns its amount to the advance's unadjusted balance.">Status</th>
                <th title="When it was reversed and why — normally because the bill was cancelled or edited.">Reversed</th>
              </tr></thead>
              <tbody>
              <?php foreach ($adjustments as $adj) {
                  $void = ((int)$adj['adjustment_status'] === 2); ?>
                <tr class="<?php echo ($void ? 'void-row' : ''); ?>">
                  <td><?php echo html_escape($adj['pay_refno']); ?></td>
                  <td><?php echo html_escape($adj['po_ref_no']); ?></td>
                  <td class="num"><?php echo cpo_money($adj['adjusted_amt']); ?></td>
                  <td><?php echo ($adj['created_on'] ? date('d-m-Y H:i', strtotime($adj['created_on'])) : ''); ?></td>
                  <td class="reason"><?php echo ($void ? 'Reversed' : 'Live'); ?></td>
                  <td class="reason">
                    <?php if ($void) { ?>
                      <?php echo ($adj['reversed_on'] ? date('d-m-Y H:i', strtotime($adj['reversed_on'])) : ''); ?>
                      <?php if ($adj['reverse_reason']) { ?><br><small><?php echo html_escape($adj['reverse_reason']); ?></small><?php } ?>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php } ?>

        <!-- ============ 5. Ledger ============ -->
        <?php
        /* $ledger can come back empty for a PO with no bill and no advance. The cells
           below dereference it, so fall back to a zeroed position rather than emitting
           an undefined-index notice into the middle of the table. */
        if (empty($ledger)) {
            $ledger = array('po_value' => 0, 'billed' => 0, 'advance' => 0, 'adjusted' => 0,
                            'payable'  => 0, 'receivable' => 0, 'position' => 'Open');
        }
        $pos   = $ledger['position'];
        $klass = ($pos === 'Nil' ? 'done' : ($pos === 'Payable' ? 'void' : 'pending'));
        ?>
        <div class="stage <?php echo $klass; ?>">
          <h4><span class="stage-no">5</span> Vendor Ledger
            <?php echo cpo_info('Where the account stands',
              '<span class="formula">Payable = Billed &minus; Adjusted</span><br>'
            . '<span class="formula">Receivable = Advance &minus; Adjusted</span>'
            . '<br><br><b>Nil</b> &mdash; advance and bill match. Settled, nothing to do.<br>'
            . '<b>Payable</b> &mdash; the bill exceeds the advance applied. Pay the balance.<br>'
            . '<b>Receivable</b> &mdash; you paid more than has been billed. The supplier owes you '
            . 'metal, or holds the surplus against the next bill.<br>'
            . '<b>Open</b> &mdash; no bill yet. Waiting on delivery.'
            . '<br><br>Everything here is derived from the live documents above; nothing is stored '
            . 'separately. A cancelled bill or reversed adjustment moves these figures at once.'); ?>
          </h4>
          <div class="row">
            <div class="col-md-8">
              <table class="table table-bordered table-condensed">
                <tr>
                  <th class="num" title="Total value of the PO lines in stage 1. Zero on an Unfixed PO, which has no agreed rate yet.">PO Value</th>
                  <th class="num" title="Total of the live supplier bills raised against this PO. Cancelled bills are excluded.">Billed</th>
                  <th class="num" title="Total of the live advances paid against this PO. Cancelled payments are excluded.">Advance</th>
                  <th class="num" title="How much advance has been set against bills. Reversed adjustments are excluded.">Adjusted</th>
                  <th class="num" title="Billed minus Adjusted — what you still owe the supplier.">Payable</th>
                  <th class="num" title="Advance minus Adjusted — what the supplier still owes you, in metal or money.">Receivable</th>
                  <th title="Nil = settled. Payable = you owe. Receivable = supplier owes you. Open = no bill yet.">Position</th>
                </tr>
                <tr>
                  <td class="num"><?php echo cpo_money($ledger['po_value']); ?></td>
                  <td class="num"><?php echo cpo_money($ledger['billed']); ?></td>
                  <td class="num"><?php echo cpo_money($ledger['advance']); ?></td>
                  <td class="num"><?php echo cpo_money($ledger['adjusted']); ?></td>
                  <td class="num"><?php echo cpo_money($ledger['payable']); ?></td>
                  <td class="num"><?php echo cpo_money($ledger['receivable']); ?></td>
                  <td>
                    <?php
                    $badge = array('Nil' => 'bg-green', 'Payable' => 'bg-red', 'Receivable' => 'bg-aqua', 'Open' => 'bg-gray');
                    ?>
                    <span class="badge <?php echo (isset($badge[$pos]) ? $badge[$pos] : 'bg-gray'); ?>"><?php echo $pos; ?></span>
                  </td>
                </tr>
              </table>
              <p class="help-block">
                Payable = Billed &minus; Adjusted &nbsp;&middot;&nbsp; Receivable = Advance &minus; Adjusted.
                Figures are derived from live documents, so a cancelled bill or reversed
                adjustment is reflected here immediately.
              </p>
            </div>
          </div>
        </div>

      </div>
    </div>

  </section>
</div>

<script>
$(function () {
    /* Nothing initialises Bootstrap popovers globally in this application, so the "!"
       markers are wired up here. If the plugin is missing the markers still work — the
       title attribute falls back to a native browser tooltip, which is why the heading
       text is in `title` and the detail is in `data-content`. */
    if ($.fn.popover) {
        $('.cpo-trace .info-mark').popover();

        // A popover left open while the pointer moves to the next marker overlaps it.
        $('.cpo-trace .info-mark').on('show.bs.popover', function () {
            $('.cpo-trace .info-mark').not(this).popover('hide');
        });
    }

    /* Column headings use plain title tooltips — no init needed — but Bootstrap's
       tooltip reads better if it happens to be available. */
    if ($.fn.tooltip) {
        $('.cpo-trace th[title], .cpo-trace .summary b[title]').tooltip({
            container: 'body',
            placement: 'top'
        });
    }
});
</script>
