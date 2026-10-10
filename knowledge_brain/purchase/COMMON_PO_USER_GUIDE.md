# Common PO — User Guide
### Buying bullion at a rate fixed in advance

**For:** Purchase staff, Accounts staff, Store staff
**Applies to:** eTail version 1.1.1.0258 and above

---

## 1. What is a Common PO?

Sometimes you agree a gold or silver rate with your supplier **today**, but the
metal arrives **later**.

Example:

> On 1st August you agree with your supplier:
> *"We will buy 100 grams of fine gold at ₹12,000 per gram."*
> The gold will be delivered on 15th August.

A **Common PO** records that agreement in the system.

It lets you:

1. Write down the rate you agreed
2. Pay the supplier an advance against it
3. Receive the metal later
4. Make the supplier bill
5. Let the system **automatically** set the advance against the bill

Without a Common PO, you would have to remember the rate, remember the advance,
and adjust it by hand. The system now does all of that for you.

---

## 2. Two types of Common PO

| Type | What it means | When to use |
|---|---|---|
| **Fixed** | You agreed a rate today. Example: ₹12,000 per gram | Most common |
| **Unfixed** | You agreed only the **quantity**. The rate will be decided later | When the rate is not settled yet |

For an **Unfixed** PO, you leave the rate empty. You will enter it later, when
you make the supplier bill.

---

## 3. Before you start (one-time setup)

Ask your admin to check these **once**. If they are not done, the screens will
not work properly.

| What | Where | Why |
|---|---|---|
| Switch on the feature | Settings → `common_po_enabled` = 1 | The Common PO tick box will not appear otherwise |
| Set **Purchase Calculation Type** on every supplier | Supplier master | Usually **Purchase Touch**. Without it you cannot add items to a bill |
| Mark your bullion categories | Category master → Category Type = **Bullion** | So the correct categories appear |
| Link purity to category | Category ↔ Purity mapping | So the purity list is not empty |
| Give the rate-override right (only if needed) | Settings → Profile Access → **Common PO Rate Override** | See section 10 |

---

## 4. Step 1 — Create the Common PO

**Go to:** Purchase → Create Purchase Order

1. Tick the **Common PO** box at the top
2. Choose the **Supplier**
3. Choose the **Due Date**
4. Fill the item row:

| Field | What to enter |
|---|---|
| Category | Your bullion category, e.g. BULLION GOLD |
| Purity | e.g. 99.5000 |
| Product | e.g. FINE GOLD |
| Design / Sub Design / Size | Only if you use them |
| Pcs | Number of pieces |
| Gross Wt | Total weight in grams |
| Stone Wt | Usually 0 for bullion |
| Rate Type | **Fixed** or **Unfixed** |
| Rate / Gram | Only for Fixed |

5. Click **Add Bullion Item**
6. Click **Save**

### What fills in by itself

You do **not** type these. The system works them out:

- **Net Wt** = Gross Wt − Stone Wt
- **Pure Wt** = Net Wt × Purity ÷ 100
- **Tax %** — taken from the product's tax group
- **Taxable / Tax / Total**

### Why "Pure Wt" matters

Gold is bought on **pure (fine) weight**, not total weight.

> 100 grams of 99.50 purity gold contains **99.500 grams** of pure gold.
> You pay for 99.500 g, not 100 g.
>
> 99.500 × ₹12,000 = **₹11,94,000** (not ₹12,00,000)

The system does this for you. **Do not change the Rate Basis** unless your
supplier agreement genuinely says gross or net weight.

---

## 5. Step 2 — Get it approved

A Common PO must be **approved** before you can pay an advance or make a bill.

**Go to:** Purchase → MD Approval Dashboard → find your PO → **Approve**

Until it is approved it will **not appear** in the payment or billing screens.
This is deliberate — nobody should pay money against an agreement that has not
been signed off.

---

## 6. Step 3 — Pay the advance

**Go to:** Purchase → Smith & Supplier Payment → Add

1. Click **ADVANCE** at the top (next to BILL and RECEIPT)
2. Choose the **Supplier**
3. Choose the **Purchase Order** from the list

The screen fills in:

```
PO Date · Committed Wt · Rate Type · Status
PO Value · Advance Already Paid · Balance
```

4. **Advance Amount** is filled with the full balance. Change it if you are
   paying only part
5. Enter how you are paying on the right — Cash, Net Banking or Cheque
6. Click **Save**

### What happens

- The supplier ledger shows the advance
- The PO status becomes **Advance Paid**
- The supplier now owes you the metal

> You cannot pay more than the PO value. If you try, the system will stop you.
> (An Unfixed PO has no value yet, so no limit applies.)

---

## 7. Step 4 — Receive the metal (GRN)

Make the GRN exactly as you do for any other purchase. **Nothing changes here.**

The GRN and the Common PO are joined together on the **supplier bill** in the
next step, so there is no extra field to fill on the GRN screen.

---

## 8. Step 5 — Make the supplier bill

**Go to:** Purchase → Supplier Bill Entry

### On the BILL DETAILS tab

1. Choose the **Supplier**
2. Choose the **GRN** ⚠️ **This is required.** Without a GRN all amounts stay 0
3. Purchase Type → **Bullion Purchase**
4. Against Order → **Yes**

### On the ITEM DETAILS tab

5. Choose the **Order** — your Common PO number

**Everything now fills in by itself** from the PO: category, purity, product,
pieces, weights, rate, tax.

6. Check the figures
7. Click **Add Item**
8. Click **Save**

### What happens when you save

✅ The advance is **automatically** set against the bill
✅ The supplier ledger updates
✅ The PO status becomes **Billed**

You do not have to adjust anything by hand.

---

## 9. Step 6 — Check the result

**Go to:** Purchase → Purchase Order List

Look at the **Ledger** column:

| What you see | What it means | What to do |
|---|---|---|
| 🟢 **Nil** | Advance and bill match exactly | Nothing. It is settled |
| 🔴 **Payable** | The bill is more than the advance | Pay the balance to the supplier |
| 🔵 **Receivable** | You paid more than the bill | The supplier owes you, or holds it for the next bill |
| ⚪ **Open** | No bill yet | Waiting for delivery |

### To see the full history

Click the **Trace** button (🔗) on the PO row.

It shows the whole story on one page:

```
1. Purchase Order    what you agreed
2. Advance Payment   what you paid
3. GRN               what arrived
4. Supplier Bill     what you were billed
5. Vendor Ledger     what is still owed
```

Cancelled documents are shown with a line through them, so you can always see
what happened and why.

---

## 10. Changing the rate on a bill

The rate on the bill comes from the PO. **Normally you cannot change it** — that
is the point of fixing a rate in advance.

If someone genuinely needs to change it, an admin must give them the
**Common PO Rate Override** permission in Settings → Profile Access.

Without that permission the rate box is locked, and the system will refuse to
save a bill with a different rate.

---

## 11. Cancelling something

You must cancel in **reverse order** — the last thing first.

| You want to cancel | You must first cancel |
|---|---|
| The **bill** | Nothing. You can cancel it |
| The **advance** | The bill (if the advance was already set against it) |
| The **PO** | The bill, then the advance |

When you cancel a bill, the advance is **automatically returned** to the PO and
can be used again. Nothing is lost.

If you try to cancel in the wrong order, the system tells you exactly which
document to cancel first.

---

## 12. Messages you may see

| Message | What it means | What to do |
|---|---|---|
| *"No GRN is selected…"* | You have not chosen a GRN | Go to Bill Details and choose one |
| *"…is a Bullion Purchase Order"* | Purchase Type is wrong | Set Purchase Type to Bullion Purchase |
| *"Purchase Calculation Type is not set…"* | Supplier setup missing | Ask admin to set it on the supplier |
| *"All line(s) … are already added"* | You already added this PO's items | Nothing. Do not add twice |
| *"Advance exceeds the outstanding balance"* | Paying more than the PO value | Reduce the amount |
| *"Overriding an agreed rate requires…"* | You changed the rate without permission | Put the PO rate back, or ask admin |
| *"…has a live supplier bill"* | Cancelling in the wrong order | Cancel the bill first |
| *"This product has no purchase tax group"* | Product setup missing | Ask admin to set the tax group |

---

## 13. Things to remember

1. **Approve the PO first.** Nothing works until it is approved
2. **Always choose a GRN** on the bill, or every amount will be zero
3. **Set Purchase Type to Bullion Purchase** before recalling the PO
4. **Do not change the rate** unless you have permission
5. **Do not add the same PO item twice** — the bill would double
6. **Cancel in reverse order** — bill, then advance, then PO
7. **Check the Ledger column** after billing. It should say **Nil**

---

## 14. A full example

```
1 Aug   Create Common PO 26-00002
        100.000 g fine gold, 99.50 purity, ₹12,000 per gram
        Pure weight 99.500 g  →  ₹11,94,000 + 3% GST = ₹12,29,820

1 Aug   Approve the PO

2 Aug   Pay advance of ₹12,29,820
        Supplier ledger: advance ₹12,29,820
        Supplier owes you 100 g of gold

15 Aug  Gold arrives. Make the GRN

15 Aug  Make the supplier bill against PO 26-00002
        Everything fills in automatically
        Bill total ₹12,29,820

        Advance adjusted automatically: ₹12,29,820
        Balance to pay: ₹0.00
        Ledger column: 🟢 Nil

        Finished. Nothing more to do.
```

---

## Need help?

If a screen does not behave as described here, note down:

- The PO number
- The screen you were on
- The exact message shown

and give it to your system administrator.
