<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * WinPixel API Configuration — Source Version (retail_v5)
 * Visual search service by Logimax Technologies
 *
 * NOTE: API key and client_code will be updated after
 * client registration in the WinPixel PostgreSQL database.
 * For local demo, use localhost:8000 and dev-mode key.
 */
$config['winpixel_api_url']     = 'https://winpixel.logimaxindia.com';
$config['winpixel_api_key']     = 'a1ca165bb4b4efd5663af1385f13bd763690f18041989b58745061f8e95cf009'; // TODO: Replace after client registration
$config['winpixel_client_code'] = 'retail_dev';
$config['winpixel_timeout']     = 90; // seconds (board-insights needs 30-60s for large DBs)
