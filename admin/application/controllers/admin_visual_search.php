<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Visual Search — WinPixel Integration (Source Version)
 *
 * Provides AI-powered image search for jewellery tags.
 * Proxies requests to WinPixel API (server-side) to keep API key hidden.
 *
 * Schema difference from AMS:
 *   - No ret_collection table — uses ret_sub_design_master instead
 *   - Hierarchy: Category → Product → Design → Sub-Design
 */
class Admin_visual_search extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		ini_set('date.timezone', 'Asia/Calcutta');
		$this->load->model('admin_settings_model');

		// Standard auth check (matches existing retail_v5 pattern)
		if (!$this->session->userdata('is_logged')) {
			redirect('admin/login');
		} elseif ($this->session->userdata('access_time_from') != NULL
				  && $this->session->userdata('access_time_from') != "") {
			$now  = time();
			$from = $this->session->userdata('access_time_from');
			$to   = $this->session->userdata('access_time_to');
			if (!($now > $from && $now < $to)) {
				$this->session->set_flashdata('login_errMsg', 'Exceeded allowed access time!!');
				redirect('chit_admin/logout');
			}
		}

		$this->load->config('winpixel');
	}

	/**
	 * GET /admin_visual_search — render search page
	 */
	public function index()
	{
		$data['main_content'] = 'visual_search/index';
		$data['page_title']   = 'Visual Search';
		$this->load->view('layout/template', $data);
	}

	/**
	 * POST /admin_visual_search/search — proxy image to WinPixel API
	 *
	 * Accepts multipart file upload, forwards to WinPixel via cURL,
	 * returns JSON response directly to browser.
	 */
	public function search()
	{
		// Validate file upload
		if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			$error_code = isset($_FILES['image']['error']) ? $_FILES['image']['error'] : 'no_file';
			$error_map = [
				UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
				UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive',
				UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
				UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
				UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
				UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
			];
			$detail = isset($error_map[$error_code]) ? $error_map[$error_code] : 'Unknown error (code: ' . $error_code . ')';
			log_message('error', 'Visual Search upload failed: ' . $detail
				. ' | post_max_size=' . ini_get('post_max_size')
				. ' | upload_max_filesize=' . ini_get('upload_max_filesize')
				. ' | file_uploads=' . ini_get('file_uploads')
				. ' | FILES keys=' . implode(',', array_keys($_FILES))
				. ' | REQUEST_METHOD=' . $_SERVER['REQUEST_METHOD']
				. ' | CONTENT_LENGTH=' . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A')
			);
			echo json_encode(['status' => false, 'msg' => 'Upload error: ' . $detail]);
			return;
		}

		// Validate file type
		$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
		if (!in_array($_FILES['image']['type'], $allowed_types)) {
			echo json_encode(['status' => false, 'msg' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, BMP']);
			return;
		}

		// Validate file size (10MB max)
		if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
			echo json_encode(['status' => false, 'msg' => 'File too large. Maximum 10MB allowed']);
			return;
		}

		$api_url = $this->config->item('winpixel_api_url') . '/api/search';
		$api_key = $this->config->item('winpixel_api_key');
		$timeout = $this->config->item('winpixel_timeout');

		// Build cURL multipart request
		$cfile = new CURLFile(
			$_FILES['image']['tmp_name'],
			$_FILES['image']['type'],
			$_FILES['image']['name']
		);

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $api_url,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => ['file' => $cfile],
			CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $api_key],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		$response  = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_err  = curl_error($ch);
		curl_close($ch);

		if ($curl_err) {
			log_message('error', 'WinPixel API cURL error: ' . $curl_err);
			echo json_encode(['status' => false, 'msg' => 'Search service connection failed']);
			return;
		}

		if ($http_code !== 200) {
			log_message('error', 'WinPixel API returned HTTP ' . $http_code . ': ' . $response);
			echo json_encode(['status' => false, 'msg' => 'Search service error (HTTP ' . $http_code . ')']);
			return;
		}

		// Decode WinPixel response to enrich with local ERP details
		$data = json_decode($response, true);
		if ($data && !empty($data['results'])) {
			$data['results'] = $this->_enrich_search_results($data['results']);
		}

		header('Content-Type: application/json');
		echo json_encode($data);
	}

	/**
	 * Enrich search results with tag details from local MySQL.
	 * Fetches category, purity, karigar, stock age, status etc. in one batch query.
	 *
	 * @param array $results  Array of search result items (each has tag_code)
	 * @return array  Enriched results
	 */
	private function _enrich_search_results($results)
	{
		// Collect tag codes from results
		$tag_codes = [];
		foreach ($results as $r) {
			if (!empty($r['tag_code'])) {
				$tag_codes[] = $r['tag_code'];
			}
		}
		if (empty($tag_codes)) return $results;

		// Detect optional tables/columns
		$has_stock_codes = $this->db->table_exists('ret_stock_codes');
		$stock_select = $has_stock_codes
			? "IFNULL(stc.stock_code, '') AS stock_code,"
			: "'' AS stock_code,";
		$stock_join = $has_stock_codes
			? "LEFT JOIN ret_stock_codes stc ON stc.id_stock_code = tag.tag_stock_code"
			: "";

		$has_sub_design = $this->db->table_exists('ret_sub_design_master');
		$sd_select = $has_sub_design ? "IFNULL(sd.sub_design_name, '') AS sub_design_name," : "'' AS sub_design_name,";
		$sd_join = $has_sub_design ? "LEFT JOIN ret_sub_design_master sd ON sd.id_sub_design = tag.id_sub_design" : "";

		$has_lot_detail = $this->db->table_exists('ret_lot_inwards_detail');
		if ($has_lot_detail) {
			$lot_join = "LEFT JOIN ret_lot_inwards_detail ld ON ld.id_lot_inward_detail = tag.id_lot_inward_detail
				LEFT JOIN ret_lot_inwards l ON l.lot_no = ld.lot_no";
		} else {
			$lot_join = "LEFT JOIN ret_lot_inwards l ON l.lot_no = tag.lot_id";
		}

		// Escape tag codes for IN clause
		$escaped = array_map(function($tc) {
			return "'" . $this->db->escape_str($tc) . "'";
		}, $tag_codes);
		$in_clause = implode(',', $escaped);

		$sql = $this->db->query("
			SELECT
				tag.tag_code,
				tag.tag_status AS tag_status_raw,
				CASE
					WHEN tag.tag_status = 0 THEN 'On Sale'
					WHEN tag.tag_status = 1 THEN 'Sold Out'
					WHEN tag.tag_status = 2 THEN 'Deleted'
					WHEN tag.tag_status = 3 THEN 'Other Issue'
					WHEN tag.tag_status = 4 THEN 'In Transit'
					WHEN tag.tag_status = 5 THEN 'Deleted for Stock'
					WHEN tag.tag_status = 6 THEN 'Sales Return'
					WHEN tag.tag_status = 7 THEN 'Stock Issue'
					WHEN tag.tag_status = 8 THEN 'Repair Item'
					WHEN tag.tag_status = 9 THEN 'Purchase Return'
					WHEN tag.tag_status = 10 THEN 'EDA Sale'
					ELSE '-'
				END AS tag_status_text,
				$stock_select
				IFNULL(pur.purity, '') AS purity,
				IFNULL(cat.name, '') AS category_name,
				$sd_select
				IFNULL(k.code_karigar, '') AS karigar_code,
				DATEDIFF(CURDATE(), tag.tag_datetime) AS stock_age_days,
				DATE_FORMAT(tag.tag_datetime, '%d-%m-%Y') AS tag_date,
				(SELECT DATE_FORMAT(MAX(b.bill_date), '%d-%m-%Y')
				 FROM ret_bill_details bd
				 INNER JOIN ret_billing b ON b.bill_id = bd.bill_id
				 WHERE bd.tag_id = tag.tag_id AND b.bill_status IN (1,3)
				) AS sold_date,
				ROUND(IFNULL(tag.gross_wt, 0) - IFNULL(tag.net_wt, 0), 3) AS less_wt
			FROM ret_taging tag
			$stock_join
			LEFT JOIN ret_purity pur ON pur.id_purity = tag.purity
			LEFT JOIN ret_product_master p ON p.pro_id = tag.product_id
			LEFT JOIN ret_category cat ON cat.id_ret_category = p.cat_id
			$sd_join
			$lot_join
			LEFT JOIN ret_karigar k ON k.id_karigar = l.gold_smith
			WHERE tag.tag_code IN ($in_clause)
		");

		// Index by tag_code for fast lookup
		$details_map = [];
		foreach ($sql->result_array() as $row) {
			$details_map[$row['tag_code']] = $row;
		}

		// Merge details into each result
		foreach ($results as &$r) {
			if (!empty($r['tag_code']) && isset($details_map[$r['tag_code']])) {
				$r = array_merge($r, $details_map[$r['tag_code']]);
			}
		}
		unset($r);

		return $results;
	}

	/**
	 * POST /admin_visual_search/feedback — proxy vote to WinPixel API
	 *
	 * Expects JSON: {session_id, point_id, tag_code, feedback}
	 */
	public function feedback()
	{
		$payload = json_encode([
			'session_id' => $this->input->post('session_id'),
			'point_id'   => intval($this->input->post('point_id')),
			'tag_code'   => $this->input->post('tag_code'),
			'feedback'   => $this->input->post('feedback'),
		]);

		$api_url = $this->config->item('winpixel_api_url') . '/api/search/feedback';
		$api_key = $this->config->item('winpixel_api_key');

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $api_url,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_HTTPHEADER     => [
				'X-API-Key: ' . $api_key,
				'Content-Type: application/json',
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		$response  = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_err  = curl_error($ch);
		curl_close($ch);

		if ($curl_err || $http_code !== 200) {
			log_message('error', 'WinPixel feedback error: ' . ($curl_err ?: "HTTP $http_code"));
			echo json_encode(['status' => false, 'msg' => 'Failed to record vote']);
			return;
		}

		header('Content-Type: application/json');
		echo $response;
	}

	/**
	 * POST /admin_visual_search/refine — proxy refine request to WinPixel API
	 *
	 * Expects JSON: {session_id}
	 */
	public function refine()
	{
		$payload = json_encode([
			'session_id' => $this->input->post('session_id'),
		]);

		$api_url = $this->config->item('winpixel_api_url') . '/api/search/refine';
		$api_key = $this->config->item('winpixel_api_key');

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $api_url,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_HTTPHEADER     => [
				'X-API-Key: ' . $api_key,
				'Content-Type: application/json',
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		$response  = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_err  = curl_error($ch);
		curl_close($ch);

		if ($curl_err || $http_code !== 200) {
			log_message('error', 'WinPixel refine error: ' . ($curl_err ?: "HTTP $http_code: $response"));
			$detail = 'Refinement failed';
			// Try to extract detail from API response
			$decoded = json_decode($response, true);
			if ($decoded && isset($decoded['detail'])) {
				$detail = $decoded['detail'];
			}
			echo json_encode(['status' => false, 'msg' => $detail]);
			return;
		}

		header('Content-Type: application/json');
		echo $response;
	}

	/**
	 * POST /admin_visual_search/tag_details — fetch live tag details from ERP
	 *
	 * Expects: tag_code (e.g., "209-00210")
	 * Returns: JSON with all tag details fetched live from the database
	 *
	 * SOURCE VERSION SCHEMA:
	 *   - No ret_collection table — uses ret_sub_design_master
	 *   - Hierarchy: Category → Product → Design → Sub-Design
	 *   - ret_taging has id_sub_design column (not in AMS)
	 */
	public function tag_details()
	{
		$tag_code = $this->input->post('tag_code');
		if (empty($tag_code)) {
			echo json_encode(['status' => false, 'msg' => 'tag_code is required']);
			return;
		}

		// Sanitize
		$tag_code = $this->db->escape_str($tag_code);

		// SV doesn't have ret_stock_codes — detect and adapt
		$has_stock_codes = $this->db->table_exists('ret_stock_codes');

		// Build stock_code SELECT + JOIN + WHERE fragments
		$stock_select = $has_stock_codes
			? "IFNULL(stc.stock_code, '') AS stock_code,"
			: "'' AS stock_code,";
		$stock_join = $has_stock_codes
			? "LEFT JOIN ret_stock_codes stc ON stc.id_stock_code = tag.tag_stock_code"
			: "";
		$stock_where = $has_stock_codes
			? "(tag.tag_code = '$tag_code' OR stc.stock_code = '$tag_code')"
			: "tag.tag_code = '$tag_code'";

		$sql = $this->db->query("
			SELECT
				tag.tag_id,
				tag.tag_code,
				tag.product_id,
				tag.design_id,
				tag.id_sub_design,
				tag.gross_wt,
				tag.net_wt,
				tag.piece,
				tag.tag_status AS tag_status_raw,
				CASE
					WHEN tag.tag_status = 0 THEN 'On Sale'
					WHEN tag.tag_status = 1 THEN 'Sold Out'
					WHEN tag.tag_status = 2 THEN 'Deleted'
					WHEN tag.tag_status = 3 THEN 'Other Issue'
					WHEN tag.tag_status = 4 THEN 'In Transit'
					WHEN tag.tag_status = 5 THEN 'Deleted for Stock'
					WHEN tag.tag_status = 6 THEN 'Sales Return'
					WHEN tag.tag_status = 7 THEN 'Stock Issue'
					WHEN tag.tag_status = 8 THEN 'Repair Item'
					WHEN tag.tag_status = 9 THEN 'Purchase Return'
					WHEN tag.tag_status = 10 THEN 'EDA Sale'
					WHEN tag.tag_status = 11 THEN 'Tag Booked for Advance'
					WHEN tag.tag_status = 13 THEN 'Added to Pocket'
					WHEN tag.tag_status = 14 THEN 'Section Transfer'
					WHEN tag.tag_status = 15 THEN 'Tag Parts'
					WHEN tag.tag_status = 17 THEN 'Metal Issue'
					ELSE '-'
				END AS tag_status_text,
				$stock_select
				IFNULL(pur.purity, '') AS purity,
				IFNULL(p.product_name, '') AS product_name,
				IFNULL(cat.name, '') AS category_name,
				IFNULL(des.design_name, '') AS design_name,
				IFNULL(sd.sub_design_name, '') AS sub_design_name,
				IFNULL(br.name, '') AS branch_name,
				IFNULL(k.code_karigar, '') AS karigar_code,
				tag.tag_datetime,
				DATE_FORMAT(tag.tag_datetime, '%d-%m-%Y') AS tag_date,
				DATEDIFF(CURDATE(), tag.tag_datetime) AS stock_age_days,
				(SELECT DATE_FORMAT(MAX(b.bill_date), '%d-%m-%Y')
				 FROM ret_bill_details bd
				 INNER JOIN ret_billing b ON b.bill_id = bd.bill_id
				 WHERE bd.tag_id = tag.tag_id AND b.bill_status IN (1,3)
				) AS sold_date,
				ROUND(IFNULL(tag.gross_wt, 0) - IFNULL(tag.net_wt, 0), 3) AS less_wt,
				IFNULL(CONCAT(sz.value, '-', sz.name), '') AS size_name,
				IFNULL(timg.image, '') AS tag_image
			FROM ret_taging tag
			$stock_join
			LEFT JOIN ret_purity pur ON pur.id_purity = tag.purity
			LEFT JOIN ret_product_master p ON p.pro_id = tag.product_id
			LEFT JOIN ret_category cat ON cat.id_ret_category = p.cat_id
			LEFT JOIN ret_design_master des ON des.design_no = tag.design_id
			LEFT JOIN ret_sub_design_master sd ON sd.id_sub_design = tag.id_sub_design
			LEFT JOIN branch br ON br.id_branch = tag.current_branch
			LEFT JOIN ret_lot_inwards_detail ld ON ld.id_lot_inward_detail = tag.id_lot_inward_detail
			LEFT JOIN ret_lot_inwards l ON l.lot_no = ld.lot_no
			LEFT JOIN ret_karigar k ON k.id_karigar = l.gold_smith
			LEFT JOIN ret_size sz ON sz.id_size = tag.size
			LEFT JOIN ret_taging_images timg ON timg.tag_id = tag.tag_id AND timg.is_default = 1
			WHERE $stock_where
			LIMIT 1
		");

		$tag = $sql->row_array();

		if (empty($tag)) {
			echo json_encode(['status' => false, 'msg' => 'Tag not found']);
			return;
		}

		// Fetch stone details for this tag (table may not exist in all clients)
		$tag['stone_details'] = [];
		if ($this->db->table_exists('ret_tag_stone_details')) {
			$stone_sql = $this->db->query("
				SELECT
					IFNULL(s.stone_name, '') AS stone_name,
					IFNULL(sd.stone_wt, 0) AS stone_wt,
					IFNULL(sd.stone_pcs, 0) AS stone_pcs,
					IFNULL(sd.stone_rate, 0) AS stone_rate,
					IFNULL(sd.stone_amount, 0) AS stone_amount
				FROM ret_tag_stone_details sd
				LEFT JOIN ret_stone_master s ON s.id_stone = sd.id_stone
				WHERE sd.tag_id = " . intval($tag['tag_id'])
			);
			$tag['stone_details'] = $stone_sql->result_array();
		}

		// Find all branches where the same category+product+design+sub-design combo
		// is currently available (on sale). Used to show multi-branch availability.
		$tag['available_branches'] = [];
		$product_id = intval($tag['product_id'] ?? 0);
		$design_id  = intval($tag['design_id'] ?? 0);
		$sub_design = intval($tag['id_sub_design'] ?? 0);

		if ($product_id > 0 && $design_id > 0) {
			$this->db->distinct();
			$this->db->select('br.name AS branch_name', FALSE);
			$this->db->from('ret_taging t2');
			$this->db->join('branch br', 'br.id_branch = t2.current_branch', 'inner');
			$this->db->where('t2.product_id', $product_id);
			$this->db->where('t2.design_id', $design_id);
			if ($sub_design > 0) {
				$this->db->where('t2.id_sub_design', $sub_design);
			}
			$this->db->where('t2.tag_status', 0); // 0 = On Sale
			$this->db->where('br.active', 1);     // 1 = active branch
			$this->db->order_by('br.name', 'ASC');
			$branch_rows = $this->db->get()->result_array();
			$tag['available_branches'] = array_column($branch_rows, 'branch_name');
		}

		echo json_encode(['status' => true, 'data' => $tag]);
	}

	/**
	 * POST /admin_visual_search/proxy_analytics — generic analytics proxy
	 *
	 * Receives endpoint + params from JS, validates against whitelist,
	 * forwards to WinPixel API via cURL, returns JSON response.
	 * API key stays hidden from browser — server-side only.
	 */
	public function proxy_analytics()
	{
		$endpoint = $this->input->post('endpoint');
		$params   = $this->input->post('params'); // JSON string

		// Whitelist — only these analytics endpoints can be proxied
		$allowed = [
			'collection-velocity', 'tag-collections', 'branch-rotation',
			'cross-branch-aging', 'dead-stock-heatmap', 'purchase-intel',
			'age-distribution', 'branch-distribution', 'design-performance',
			'branch-ranking', 'dead-stock', 'stock-turn', 'search-accuracy',
			'stock-availability', 'suggest', 'engines', 'insights',
			'board-insights', 'ask-ai', 'fns-classification',
			'product-transfer-candidates', 'top-selling-items', 'sales-pattern-analysis',
			'search-context-insights'
		];

		if (empty($endpoint) || !in_array($endpoint, $allowed)) {
			echo json_encode(['status' => false, 'msg' => 'Invalid or missing endpoint']);
			return;
		}

		// Build URL
		$api_url = $this->config->item('winpixel_api_url') . '/api/analytics/' . $endpoint;
		$api_key = $this->config->item('winpixel_api_key');
		$timeout = $this->config->item('winpixel_timeout');

		// Parse params
		$query_params = [];
		if (!empty($params)) {
			$decoded = json_decode($params, true);
			if (is_array($decoded)) {
				$query_params = $decoded;
			}
		}

		// For suggest endpoint, use POST with query params
		// For others, use GET with query string
		$is_post = in_array($endpoint, ['suggest', 'insights']);

		if (!$is_post && !empty($query_params)) {
			$api_url .= '?' . http_build_query($query_params);
		}

		// Release session lock before long-running cURL call
		// Without this, concurrent AJAX calls serialize on session file lock
		session_write_close();

		$ch = curl_init();
		$curl_opts = [
			CURLOPT_URL            => $is_post ? $api_url . '?' . http_build_query($query_params) : $api_url,
			CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $api_key],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_SSL_VERIFYPEER => true,
		];

		if ($is_post) {
			$curl_opts[CURLOPT_POST] = true;
			$curl_opts[CURLOPT_POSTFIELDS] = '';
		}

		curl_setopt_array($ch, $curl_opts);

		$response  = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_err  = curl_error($ch);
		curl_close($ch);

		if ($curl_err) {
			log_message('error', 'WinPixel analytics proxy cURL error: ' . $curl_err);
			echo json_encode(['status' => false, 'msg' => 'Analytics service connection failed']);
			return;
		}

		if ($http_code !== 200) {
			log_message('error', 'WinPixel analytics proxy HTTP ' . $http_code . ': ' . $response);
			echo json_encode(['status' => false, 'msg' => 'Analytics service error (HTTP ' . $http_code . ')']);
			return;
		}

		// Pass WinPixel JSON directly to browser
		header('Content-Type: application/json');
		echo $response;
	}

	/**
	 * GET /admin_visual_search/boards — render Suggestive Boards page
	 *
	 * Full analytics dashboard with filterable boards:
	 * Fast Movers, Branch Rotation, Purchase Intel, FNS Classification,
	 * Stock Availability, Design Performance, Age Analysis
	 */
	public function boards()
	{
		$data['main_content'] = 'visual_search/boards';
		$data['page_title']   = 'Suggestive Boards';
		$this->load->view('layout/template', $data);
	}

	/**
	 * POST /admin_visual_search/get_filter_options — local MySQL filter data
	 *
	 * Returns branches, categories, products, sub-designs (collections),
	 * and karigars for the filter modal dropdowns.
	 * Fetched from local DB — no WinPixel dependency.
	 */
	public function get_filter_options()
	{
		$result = [];

		// Branches (active only)
		$this->db->select('id_branch, name');
		$this->db->from('branch');
		$this->db->where('active', 1); // 1 = active branch
		$this->db->order_by('name', 'ASC');
		$result['branches'] = $this->db->get()->result_array();

		// Categories (active)
		$this->db->select('id_ret_category, name');
		$this->db->from('ret_category');
		$this->db->where('status', 1); // 1 = active
		$this->db->order_by('name', 'ASC');
		$result['categories'] = $this->db->get()->result_array();

		// Products (with cat_id for cascade filtering)
		$this->db->select('pro_id, product_name, cat_id');
		$this->db->from('ret_product_master');
		$this->db->where('product_status', 1); // 1 = active product
		$this->db->order_by('product_name', 'ASC');
		$result['products'] = $this->db->get()->result_array();

		// Designs (with product_id for cascade filtering)
		$this->db->select('design_no, design_name, product_id');
		$this->db->from('ret_design_master');
		$this->db->where('design_status', 1); // 1 = active design
		$this->db->order_by('design_name', 'ASC');
		$result['designs'] = $this->db->get()->result_array();

		// Sub-Designs (independent — no FK to design)
		$this->db->select('id_sub_design, sub_design_name');
		$this->db->from('ret_sub_design_master');
		$this->db->where('status', 1); // 1 = active sub-design
		$this->db->order_by('sub_design_name', 'ASC');
		$result['sub_designs'] = $this->db->get()->result_array();

		// Karigars (active only)
		$this->db->select("id_karigar, code_karigar, CONCAT(firstname, ' ', COALESCE(lastname, '')) AS name_karigar", FALSE);
		$this->db->from('ret_karigar');
		$this->db->where('status_karigar', 0); // 0 = active karigar (non-deleted)
		$this->db->order_by('firstname', 'ASC');
		$result['karigars'] = $this->db->get()->result_array();

		echo json_encode(['status' => true, 'data' => $result]);
	}
}
