/**
 * Bespoke request helpers for the 8 legacy (not-yet-migrated-to-master_crud)
 * masters fixed in this validation pass: Metal Process, Charges, Category,
 * Design, Stone Discount, Supplier Catalogue, Product, Cash Deposit.
 *
 * Unlike the 38 config-driven masters (one shared contract, see api.js),
 * every legacy controller is hand-written with its own URL pattern, action
 * casing, and response shape (some echo JSON, some redirect+flashdata with
 * no inspectable body) — so each helper here matches its specific method
 * exactly rather than forcing a generic abstraction that doesn't fit.
 */
const base = require('./api');

function baseUrl() {
  return process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';
}

async function postForm(page, url, form, headers) {
  const res = await page.request.post(url, headers ? { form, headers } : { form });
  return res;
}

/** admin_ret_catalog.php requires X-Requested-With on every update_status
 * action (CSRF hardening, FR-CAT-011) — a plain form POST gets a 400
 * "Invalid request" before it ever reaches the switch-case. */
async function postStatusUpdate(page, url) {
  return postForm(page, url, {}, { 'X-Requested-With': 'XMLHttpRequest' });
}

async function postJson(page, url, form) {
  const res = await postForm(page, url, form);
  return res.json();
}

/** Playwright's `form` option can't express repeated keys (PHP array POST
 * fields like `id_design[]=1&id_design[]=2`) — a JS array value just gets
 * stringified. Build the url-encoded body by hand instead so array fields
 * actually arrive as real PHP arrays. */
async function postFormWithArrays(page, url, fields) {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(fields)) {
    if (Array.isArray(value)) {
      for (const v of value) params.append(key, String(v));
    } else {
      params.append(key, String(value ?? ''));
    }
  }
  const res = await page.request.post(url, {
    data: params.toString(),
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
  });
  return res;
}

async function postJsonWithArrays(page, url, fields) {
  const res = await postFormWithArrays(page, url, fields);
  return res.json();
}

// ---------------------------------------------------------------------
// Metal Process Master — admin_ret_metal_process/metal_process/*
// Redirect+flashdata only (no JSON on save/update) — verified via the
// 'ajax' action, which returns ALL rows with no required params.
// ---------------------------------------------------------------------
const metalProcess = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_metal_process/metal_process/save`, {
      'process[process_name]': data.process_name ?? '',
      'process[has_charge]': '0',
      'process[charge_type]': '1',
    });
  },
  async list(page) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_metal_process/metal_process/ajax`, {});
    return body.list || [];
  },
};

// ---------------------------------------------------------------------
// Charges — admin_ret_catalog/charges/* (note capital "Add")
// Returns JSON {status, msg} directly — no list endpoint needed.
// ---------------------------------------------------------------------
const charges = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/charges/Add`, {
      charge_name: data.charge_name ?? '',
      charge_code: data.charge_code ?? '',
      charge_description: '',
      value_charge: '1',
      tag_display: '1',
      charge_tax: '0',
    });
    return { ok: body.status === true, body };
  },
  /** getActiveChargesList() — real read endpoint; Add's response has no id. */
  async list(page) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/charges/getActiveChargesList`, {});
    return body || [];
  },
  /** Now deactivates (status=0) instead of a hard DELETE — fixed 2026-07-30. */
  async delete(page, idCharge) {
    return postJson(page, `${baseUrl()}/admin_ret_catalog/charges/delete`, { id_charge: String(idCharge) });
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/charges/update_status/${id}/${status}`);
  },
};

// ---------------------------------------------------------------------
// Category — admin_ret_catalog/category/add
// Returns JSON {message, class, title} — class:'success' on success.
// ---------------------------------------------------------------------
const category = {
  async add(page, data) {
    // I-GMSJ-005: Purity is now required — auto-fill it when a test's own
    // concern is something else entirely (metal, tax group, name validation,
    // etc.) so those callers don't all need updating just to keep passing.
    let idPurity = data.id_purity;
    if (idPurity === undefined || idPurity === '') {
      const purity = await base.createRecord(page, 'purity');
      idPurity = purity.id_purity;
    }
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/category/add`, {
      name: data.name ?? '',
      id_metal: String(data.id_metal ?? '0'),
      tgrp_id: String(data.tgrp_id ?? ''),
      cat_code: '',
      hsn_code: '',
      id_purity: String(idPurity),
    });
    return { ok: body.class === 'success', body };
  },
  /** getActiveCategorymtr() — real read endpoint, used to fetch a valid
   * id_ret_category for Product's required cat_id FK. */
  async activeList(page) {
    return postJson(page, `${baseUrl()}/admin_ret_catalog/category/active_category`, {});
  },
  /** getCatPurity() — returns this category's ret_metal_cat_purity rows
   * ({id_purity, purity, id_category}), used to prove purity associations
   * survive a Delete + reactivate cycle instead of being wiped. */
  async catPurity(page, idCategory) {
    return postJson(page, `${baseUrl()}/admin_ret_catalog/category/cat_purity`, {
      id_category: String(idCategory),
    });
  },
  /** Like every other master, Category's "delete" now deactivates
   * (status=0) instead of a hard DELETE — fixed 2026-07-22; see
   * legacy-delete-to-deactivate.spec.js. Blocked while referenced in
   * ret_taging (getItemsinTagDetails). */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/category/delete/${id}`, {});
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/category/update_status/${id}/${status}`);
  },
};

// ---------------------------------------------------------------------
// Stone Discount — admin_ret_catalog/stone_discount/add
// Returns JSON {message, class, title} directly.
// ---------------------------------------------------------------------
const stoneDiscount = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/stone_discount/add`, {
      stone_type: String(data.stone_type ?? ''),
      min_disc: String(data.min_disc ?? ''),
      max_disc: String(data.max_disc ?? ''),
      disc_status: '1',
    });
    return { ok: body.class === 'success', body };
  },
};

// ---------------------------------------------------------------------
// Supplier Catalogue — admin_ret_supp_catalog/supplier_catalog/save
// Returns JSON {status, message, supp_cat_id, design_code} directly.
// ---------------------------------------------------------------------
// A minimal valid 1x1 transparent PNG — the save action rejects any image
// whose decoded MIME type isn't jpg/jpeg/png, and always iterates $_POST['images']
// even when every entry is a placeholder, so a real image is required for the
// add() call to actually succeed (previously only ever exercised for its
// rejection paths, never a real success — see legacy-validation.spec.js).
const ONE_PX_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

const supplierCatalog = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_supp_catalog/supplier_catalog/save`, {
      product_id: String(data.product_id ?? ''),
      design_id: String(data.design_id ?? ''),
      subdesign_id: String(data.subdesign_id ?? ''),
      status: '1',
      weight: '', from_weight: String(data.from_weight ?? ''), to_weight: String(data.to_weight ?? ''),
      purity: '', size: '', wastage: '', display_va: '', mc_value: '', mc_type: '',
      display_mc: '', delivery_duration: '', display_duration: '', karigar: '',
      'item[0]': '1', // $_POST['item'] is iterated only inside the insertId>0 branch
      'images[0][is_default]': '1', 'images[0][value]': ONE_PX_PNG_BASE64,
    });
    return { ok: body.status === true, body };
  },
  /** ajax_getSupplierCatList($id) — real read endpoint, used to confirm
   * status after delete/reactivate. */
  async getById(page, id) {
    return postJson(page, `${baseUrl()}/admin_ret_supp_catalog/supplier_catalog/get_data_by_id/${id}`, {});
  },
  /** Now deactivates (status=0) instead of a hard DELETE + child-row wipe +
   * image unlink — fixed 2026-07-30. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_supp_catalog/supplier_catalog/delete/${id}`, {});
  },
};

// ---------------------------------------------------------------------
// Product — admin_ret_catalog/ret_product/save
// Redirect+flashdata only. Verified via 'active_list' (WHERE
// product_status=1, no required params beyond an empty id_category).
// ---------------------------------------------------------------------
const product = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_product/save`, {
      'product[cat_id]': String(data.cat_id ?? ''),
      'product[tgrp_id]': String(data.tgrp_id ?? ''),
      'product[product_name]': data.product_name ?? '',
    });
  },
  async update(page, id, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_product/update/${id}`, {
      'product[cat_id]': String(data.cat_id ?? ''),
      'product[product_name]': data.product_name ?? '',
    });
  },
  async activeList(page) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/ret_product/active_list`, { id_category: '' });
    return body || [];
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/ret_product/update_status/${id}/${status}`);
  },
  /** Now deactivates (product_status=0) instead of a hard DELETE — fixed
   * 2026-07-22; see legacy-delete-to-deactivate.spec.js. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_product/delete/${id}`, {});
  },
};

// ---------------------------------------------------------------------
// Design — admin_ret_catalog/ret_design/save
// Redirect+flashdata only. Verified via 'active_designBySearch', which
// requires a real product_id to filter by (design_name LIKE match).
// ---------------------------------------------------------------------
const design = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_design/save`, {
      'design[design_name]': data.design_name ?? '',
      'design[product_id]': String(data.product_id ?? ''),
    });
  },
  async searchByName(page, searchTxt, productId) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/ret_design/active_designBySearch`, {
      searchTxt, product_id: String(productId),
    });
    return body || [];
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/ret_design/update_status/${id}/${status}`);
  },
  /** Now deactivates (design_status=0) instead of a hard DELETE — fixed
   * 2026-07-22; see legacy-delete-to-deactivate.spec.js. The route's `id`
   * only feeds the in-stock check; the row actually updated is whichever
   * design_no is POSTed (confirmed in admin_ret_catalog.php), so both must
   * be supplied and must match. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_design/delete/${id}`, {
      design_no: String(id),
    });
  },
};

// ---------------------------------------------------------------------
// Design Mapping — admin_ret_catalog/update_product_design_mapping (bare
// method, not a switch-case action). Returns JSON {status, msg}.
// ---------------------------------------------------------------------
const designMapping = {
  async add(page, data) {
    const body = await postJsonWithArrays(page, `${baseUrl()}/admin_ret_catalog/update_product_design_mapping`, {
      id_product: data.id_product ?? '',
      'id_design[]': data.id_design ?? [],
    });
    return { ok: body.status === true, body };
  },
};

// ---------------------------------------------------------------------
// Sub Design Mapping — admin_ret_catalog/update_subdesign_mapping (bare
// method, not a switch-case action). Returns JSON {status, msg}.
// ---------------------------------------------------------------------
const subDesignMapping = {
  async add(page, data) {
    const body = await postJsonWithArrays(page, `${baseUrl()}/admin_ret_catalog/update_subdesign_mapping`, {
      id_product: data.id_product ?? '',
      id_design: data.id_design ?? '',
      'id_sub_design[]': data.id_sub_design ?? [],
    });
    return { ok: body.status === true, body };
  },
};

// ---------------------------------------------------------------------
// Sub Design — admin_ret_catalog/ret_sub_design/save|update (lowercase).
// Redirect+flashdata only. Verified via 'active_list' (WHERE status=1,
// getActiveSubDesigns() returns id_sub_design/sub_design_name).
// ---------------------------------------------------------------------
const subDesign = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_sub_design/save`, {
      'subdesign[sub_design_name]': data.sub_design_name ?? '',
    });
  },
  async update(page, id, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_sub_design/update/${id}`, {
      'subdesign[sub_design_name]': data.sub_design_name ?? '',
    });
  },
  async activeList(page) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/ret_sub_design/active_list`, {});
    return body || [];
  },
  /** Now deactivates (status=0) instead of a hard DELETE — fixed
   * 2026-07-22; see legacy-delete-to-deactivate.spec.js. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_sub_design/delete/${id}`, {});
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/ret_sub_design/update_status/${id}/${status}`);
  },
};

// ---------------------------------------------------------------------
// Weight Range — admin_ret_catalog/weight/Add|Update (capital, unlike
// most other legacy entities). Returns JSON {status, msg, weight_id}.
// ---------------------------------------------------------------------
const weight = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/weight/Add`, {
      name: data.name ?? '',
      from_weight: String(data.from_weight ?? ''),
      to_weight: String(data.to_weight ?? ''),
      id_product: String(data.id_product ?? ''),
      uom: String(data.uom ?? ''),
      weight_desc: '',
    });
    return { ok: body.status === true, body };
  },
  async update(page, id, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/weight/Update/${id}`, {
      name: data.name ?? '',
      from_weight: String(data.from_weight ?? ''),
      to_weight: String(data.to_weight ?? ''),
      id_product: String(data.id_product ?? ''),
      uom: String(data.uom ?? ''),
      weight_desc: '',
    });
    return { ok: body.status === true, body };
  },
  /** Now deactivates (active=0) instead of a hard DELETE — fixed 2026-07-30. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/weight/Delete/${id}`, {});
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/weight/update_status/${id}/${status}`);
  },
  /** weight_Edit action -> get_weight($id) — returns {weight: [{...row}]}. */
  async getById(page, id) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/weight/weight_Edit/${id}`, {});
    return (body.weight || [])[0];
  },
};

// ---------------------------------------------------------------------
// Discount Master - Wastage Discount — admin_ret_catalog/wastage_discount/
// Add|Update. Note Add posts 'disc_value'/'from_wastage'/'to_wastage' but
// Update posts 'value'/'from_range'/'to_range' — different key names for
// the same fields, confirmed in the controller, not a typo here.
// ---------------------------------------------------------------------
const wastageDiscount = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/wastage_discount/Add`, {
      disc_value: String(data.disc_value ?? ''),
      from_wastage: String(data.from_wastage ?? ''),
      to_wastage: String(data.to_wastage ?? ''),
      id_metal: String(data.id_metal ?? ''),
    });
    return { ok: body.status === true, body };
  },
  async update(page, id, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/wastage_discount/Update/${id}`, {
      value: String(data.value ?? ''),
      from_range: String(data.from_range ?? ''),
      to_range: String(data.to_range ?? ''),
      id_metal: String(data.id_metal ?? ''),
    });
    return { ok: body.status === true, body };
  },
  /** Now deactivates (status=0) instead of a hard DELETE — fixed 2026-07-30. */
  async delete(page, id) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/wastage_discount/Delete/${id}`, {});
  },
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/wastage_discount/update_status/${id}/${status}`);
  },
  /** Edit action -> get_wastage($id) — returns the row object directly. */
  async getById(page, id) {
    return postJson(page, `${baseUrl()}/admin_ret_catalog/wastage_discount/Edit/${id}`, {});
  },
};

// ---------------------------------------------------------------------
// Karigar (Vendor Registration) — admin_ret_catalog/karigar/save|update
// (lowercase). Redirect+flashdata only, no JSON — the required/duplicate
// checks added here both `return` BEFORE the controller touches
// $_POST['o_item']/['kyc_det']/['stn']/['bank'], so a minimal payload
// (just the karigar[] fields) is enough to exercise them; those child
// arrays are only needed for a full successful save. Verified via the
// real getExistingKarigars() search endpoint (matches on firstname).
// ---------------------------------------------------------------------
const karigar = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/karigar/save`, {
      'karigar[first_name]': data.first_name ?? '',
      'karigar[user_name]': data.user_name ?? '',
    });
  },
  async update(page, id, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/karigar/update/${id}`, {
      'karigar[first_name]': data.first_name ?? '',
      'karigar[user_name]': data.user_name ?? '',
    });
  },
  /** getExistingKarigars() — real search-by-firstname endpoint. */
  async search(page, searchTxt) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/getExistingKarigars`, { searchTxt });
    return body || [];
  },
};

// ---------------------------------------------------------------------
// Sub Product — admin_ret_catalog/ret_sub_product/save|update (lowercase).
// Redirect+flashdata only. Verified via 'active_list' (WHERE
// sub_pro_status=1, no required params — Add hardcodes status=1).
// ---------------------------------------------------------------------
const subProduct = {
  async add(page, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_sub_product/save`, {
      'sub_product[sub_pro_name]': data.sub_pro_name ?? '',
    });
  },
  async update(page, id, data) {
    return postForm(page, `${baseUrl()}/admin_ret_catalog/ret_sub_product/update/${id}`, {
      'sub_product[sub_pro_name]': data.sub_pro_name ?? '',
    });
  },
  async activeList(page) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/ret_sub_product/active_list`, {});
    return body || [];
  },
};

// ---------------------------------------------------------------------
// Cash Deposit — deposit/save|update/<id> route aliases (NOT the usual
// admin_ret_catalog/bank_deposit/* pattern — see routes.php). Redirect+
// flashdata only. Verified via deposit/ajax_list, whose response includes
// both the full 'deposits' array and 'max_id' (the just-inserted row's
// dep_id) — no separate lookup-by-id endpoint exists.
// ---------------------------------------------------------------------
const bankDeposit = {
  /** get_cash_in_hand — real available-balance endpoint, used so tests
   * don't hardcode a deposit amount that might exceed real dev-data cash. */
  async getCashInHand(page, branchId, cashType) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/bank_deposit/get_cash_in_hand`, {
      id_branch: String(branchId), cash_type: String(cashType),
    });
    return parseFloat(String(body.cash_in_hand).replace(/,/g, '')) || 0;
  },
  async save(page, data) {
    return postForm(page, `${baseUrl()}/deposit/save`, {
      dep_branch: String(data.dep_branch ?? ''),
      type: String(data.type ?? '1'),
      dep_amount: String(data.dep_amount ?? '0'),
      dep_bank: String(data.dep_bank ?? ''),
      dep_ref_id: data.dep_ref_id ?? '',
      dep_narration: data.dep_narration ?? '',
      dep_by: '',
      dep_date: data.dep_date ?? new Date().toISOString().slice(0, 10),
      cash_date: data.cash_date ?? new Date().toISOString().slice(0, 10),
    });
  },
  async update(page, id, data) {
    return postForm(page, `${baseUrl()}/deposit/update/${id}`, {
      dep_branch: String(data.dep_branch ?? ''),
      type: String(data.type ?? '1'),
      dep_amount: String(data.dep_amount ?? '0'),
      dep_bank: String(data.dep_bank ?? ''),
      dep_ref_id: data.dep_ref_id ?? '',
      dep_narration: data.dep_narration ?? '',
      dep_by: '',
      dep_date: data.dep_date ?? new Date().toISOString().slice(0, 10),
      cash_date: data.cash_date ?? new Date().toISOString().slice(0, 10),
    });
  },
  async list(page) {
    return postJson(page, `${baseUrl()}/deposit/ajax_list`, {});
  },
};

// ---------------------------------------------------------------------
// Re-Order / Stock Allotment Settings — admin_ret_catalog/reorder_settings/*
// Only used here to regression-guard the removed dead update_status case
// (previously wrote to ret_material.material_status).
// ---------------------------------------------------------------------
const reorderSettings = {
  async updateStatus(page, id, status) {
    return postStatusUpdate(page, `${baseUrl()}/admin_ret_catalog/reorder_settings/update_status/${id}/${status}`);
  },
};

// ---------------------------------------------------------------------
// Product Grouping — admin_ret_catalog/product_grouping/add|update
// Returns JSON {message, class, title} — class:'success' on success.
// ---------------------------------------------------------------------
const productGrouping = {
  async add(page, data) {
    const body = await postJson(page, `${baseUrl()}/admin_ret_catalog/product_grouping/add`, {
      product_grpname: data.product_grpname ?? '',
      prodgrp_metal: String(data.prodgrp_metal ?? ''),
      prodstatus: '1',
    });
    return { ok: body.class === 'success', body };
  },
};

// ---------------------------------------------------------------------
// Karigar Product Mapping — admin_ret_catalog/update_karigar_products (bare
// method). Returns JSON {status, message} — note "message" not "msg", unlike
// most other legacy endpoints (confirmed in the controller, not a typo here).
// ---------------------------------------------------------------------
const karigarProductMapping = {
  async add(page, data) {
    const body = await postJsonWithArrays(page, `${baseUrl()}/admin_ret_catalog/update_karigar_products`, {
      id_sub_design_mapping: data.id_sub_design_mapping ?? '',
      'id_karigar[]': data.id_karigar ?? [],
    });
    return { ok: body.status === true, body };
  },
};

module.exports = {
  metalProcess, charges, category, stoneDiscount, supplierCatalog, product, design,
  subDesign, weight, wastageDiscount, karigar, subProduct, bankDeposit, reorderSettings,
  designMapping, subDesignMapping, productGrouping, karigarProductMapping,
};
