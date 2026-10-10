<?php
/**
 * Fetch Logimax-Technologies Repos and Update Registry
 * 
 * Run this script on the server to fetch all repos from Logimax-Technologies
 * and update clients/registry.json
 * 
 * Usage: php fetch-logimax-tech-repos.php [github_token]
 */

// Configuration
$org = 'Logimax-Technologies';
$registry_file = __DIR__ . '/registry.json';
$token_file = '/home/retaillogimaxind/.github_token';

// Get GitHub token - check command line first, then env, then file
function getGitHubToken($token_file, $argv) {
    // Check command line argument first
    if (isset($argv[1]) && !empty($argv[1])) {
        return trim($argv[1]);
    }
    // Try environment variable
    $env_token = getenv('GITHUB_TOKEN');
    if ($env_token) {
        return $env_token;
    }
    // Try token file
    if (file_exists($token_file)) {
        return trim(file_get_contents($token_file));
    }
    return null;
}

// GitHub API request
function githubRequest($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github.v3+json',
            "Authorization: Bearer $token",
            'User-Agent: Logimax-Registry-Updater'
        ]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $http_code,
        'data' => json_decode($response, true)
    ];
}

// Main script
echo "===========================================\n";
echo "Fetching Logimax-Technologies Repositories\n";
echo "===========================================\n\n";

// Get token
$token = getGitHubToken($token_file, $argv);
if (!$token) {
    die("❌ GitHub token not found.\n   Usage: php fetch-logimax-tech-repos.php YOUR_GITHUB_TOKEN\n   Or set GITHUB_TOKEN env variable\n");
}
echo "✅ GitHub token found\n";

// Fetch all repos from org
echo "📦 Fetching repos from $org...\n";

$all_repos = [];
$page = 1;
$per_page = 100;

do {
    $result = githubRequest(
        "https://api.github.com/orgs/$org/repos?per_page=$per_page&page=$page&sort=updated",
        $token
    );
    
    if ($result['code'] !== 200) {
        // Try user repos if org fails
        $result = githubRequest(
            "https://api.github.com/users/$org/repos?per_page=$per_page&page=$page&sort=updated",
            $token
        );
    }
    
    if ($result['code'] !== 200) {
        die("❌ Failed to fetch repositories. HTTP code: {$result['code']}\n");
    }
    
    $repos = $result['data'];
    
    foreach ($repos as $repo) {
        // Determine repo type
        $type = 'unknown';
        $name = $repo['name'];
        
        if (strpos($name, 'retail') !== false) {
            $type = 'source';
        } elseif (strpos($name, 'api') !== false) {
            $type = 'api';
        } elseif (strpos($name, 'doc') !== false) {
            $type = 'docs';
        } elseif (strpos($name, 'legacy') !== false || strpos($name, 'v4') !== false || strpos($name, 'old') !== false) {
            $type = 'legacy';
        } elseif (strpos($name, 'test') !== false || strpos($name, 'demo') !== false) {
            $type = 'demo';
        }
        
        // Create ID from name
        $id = preg_replace('/[^a-z0-9]/', '_', strtolower($name));
        
        $all_repos[] = [
            'id' => $id,
            'name' => $repo['name'],
            'repo' => $repo['name'],
            'org' => $org,
            'url' => $repo['html_url'],
            'description' => $repo['description'] ?? '',
            'type' => $type,
            'default_branch' => $repo['default_branch'],
            'private' => $repo['private'],
            'updated_at' => $repo['updated_at']
        ];
        
        echo "  ✓ {$repo['name']}\n";
    }
    
    $page++;
} while (count($repos) === $per_page && $page <= 5);

echo "\n📦 Found " . count($all_repos) . " repositories\n\n";

// Read current registry
if (!file_exists($registry_file)) {
    die("❌ Registry file not found: $registry_file\n");
}

$registry = json_decode(file_get_contents($registry_file), true);
if (!$registry) {
    die("❌ Failed to parse registry.json\n");
}

// Update logimax_technologies section
$registry['logimax_technologies'] = $all_repos;
$registry['logimax_technologies_count'] = count($all_repos);
$registry['logimax_technologies_updated_at'] = date('c');

// Save registry
$json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($registry_file, $json)) {
    echo "✅ Registry updated successfully!\n";
    echo "   File: $registry_file\n";
    echo "   Logimax-Technologies repos: " . count($all_repos) . "\n";
} else {
    die("❌ Failed to write registry file\n");
}

echo "\n===========================================\n";
echo "Done!\n";
echo "===========================================\n";
