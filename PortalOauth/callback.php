<?php
$config = require 'config.php';
$crmBase    = rtrim(($config['crm_internal'] ?? $config['crm_url']), '/');
$tokenUrl   = $crmBase . '/index.php?entryPoint=sticPortalOAuthToken';
$clientId   = $config['client_id'];
$redirectUri = $config['redirect_uri'];
$error = '';
$data  = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
  $code  = $_GET['code'];
  $state = $_GET['state'] ?? '';
  if ($state !== ($_COOKIE['oauth_demo_state'] ?? '')) {
    $error = 'Invalid state parameter. Possible CSRF attack or expired session.';
  } else {
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
      ]),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) {
      $error = 'Failed to connect to token endpoint.';
    } else {
      $data = json_decode($response, true);
    }
  }
  setcookie('oauth_demo_state', '', time() - 3600, '/');
}

function h($s)
{
  return htmlspecialchars((string)$s);
}
function tableVal($val)
{
  return $val === null || $val === '' ? '<em style="color:#aaa">—</em>' : h($val);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OAuth Callback — SinergiaCRM</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f0f2f5;
      color: #333;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 20px
    }

    .card {
      background: #fff;
      max-width: 780px;
      width: 100%;
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .1);
      padding: 30px
    }

    h1 {
      font-size: 20px;
      margin-bottom: 15px;
      color: #1976d2
    }

    h2 {
      font-size: 15px;
      margin: 20px 0 8px;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 5px;
      color: #333
    }

    .msg {
      padding: 10px 14px;
      border-radius: 4px;
      margin-bottom: 15px;
      font-size: 13px
    }

    .msg-error {
      background: #fdecea;
      color: #c62828
    }

    .msg-success {
      background: #e8f5e9;
      color: #2e7d32
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4px 16px;
      font-size: 12px
    }

    .grid dt {
      color: #888;
      font-weight: 600
    }

    .grid dd {
      margin-bottom: 2px;
      word-break: break-all
    }

    code.raw {
      display: block;
      background: #263238;
      color: #89ddff;
      padding: 10px 14px;
      border-radius: 4px;
      font-size: 11px;
      word-break: break-all;
      overflow-x: auto;
      margin-top: 8px;
      white-space: pre-wrap
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-top: 8px
    }

    th,
    td {
      padding: 6px 8px;
      text-align: left;
      border-bottom: 1px solid #eee
    }

    th {
      background: #f5f5f5;
      font-weight: 600;
      color: #555
    }

    .badge {
      display: inline-block;
      padding: 1px 8px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 600
    }

    .badge-active {
      background: #c8e6c9;
      color: #2e7d32
    }

    .badge-ended {
      background: #ffcdd2;
      color: #c62828
    }

    .links {
      margin-top: 20px;
      font-size: 13px
    }

    .links a {
      color: #1976d2;
      text-decoration: none;
      margin-right: 12px
    }

    .toggle {
      cursor: pointer;
      font-size: 11px;
      color: #1976d2;
      margin-top: 6px;
      display: inline-block
    }

    .json-hidden {
      display: none
    }
  </style>
</head>

<body>
  <div class="card">

    <h1>OAuth Callback</h1>

    <?php if ($error): ?>
      <div class="msg msg-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($data && !isset($data['error'])): ?>
      <div class="msg msg-success">Authentication successful! Here is everything your app received.</div>

      <h2>Tokens</h2>
      <div class="grid">
        <dt>Access Token</dt>
        <dd><code><?= h($data['access_token']) ?></code></dd>
        <dt>Expires in</dt>
        <dd><?= h($data['expires_in']) ?> seconds</dd>
        <dt>Token Type</dt>
        <dd><?= h($data['token_type']) ?></dd>
        <dt>Refresh Token</dt>
        <dd><code><?= h($data['refresh_token']) ?></code></dd>
        <dt>Portal ID</dt>
        <dd><code><?= h($data['portal_id']) ?></code></dd>
        <dt>Portal Type</dt>
        <dd><?= h($data['portal_type']) ?></dd>
      </div>

      <?php if (!empty($data['user'])): $u = $data['user']; ?>
        <h2>User Information (<?= h($data['portal_type']) ?>)</h2>
        <div class="grid">
          <dt>ID</dt>
          <dd><code><?= h($u['id']) ?></code></dd>
          <?php if ($data['portal_type'] === 'Contact'): ?>
            <dt>First Name</dt>
            <dd><?= tableVal($u['first_name']) ?></dd>
            <dt>Last Name</dt>
            <dd><?= tableVal($u['last_name']) ?></dd>
            <dt>Birthdate</dt>
            <dd><?= tableVal($u['birthdate']) ?></dd>
            <dt>Phone Mobile</dt>
            <dd><?= tableVal($u['phone_mobile']) ?></dd>
            <dt>Language</dt>
            <dd><?= tableVal($u['stic_language_c']) ?></dd>
            <dt>Gender</dt>
            <dd><?= tableVal($u['stic_gender_c']) ?></dd>
            <dt>Age</dt>
            <dd><?= tableVal($u['stic_age_c']) ?></dd>
            <dt>ID Number</dt>
            <dd><?= tableVal($u['stic_identification_number_c']) ?></dd>
            <dt>ID Type</dt>
            <dd><?= tableVal($u['stic_identification_type_c']) ?></dd>
            <dt>Address (ZIP)</dt>
            <dd><?= tableVal($u['primary_address_postalcode']) ?></dd>
            <dt>Address (Country)</dt>
            <dd><?= tableVal($u['primary_address_country']) ?></dd>
            <dt>Address (State)</dt>
            <dd><?= tableVal($u['primary_address_state']) ?></dd>
            <dt>Address (City)</dt>
            <dd><?= tableVal($u['primary_address_city']) ?></dd>
          <?php else: ?>
            <dt>Name</dt>
            <dd><?= tableVal($u['name']) ?></dd>
            <dt>Phone Office</dt>
            <dd><?= tableVal($u['phone_office']) ?></dd>
            <dt>Phone Alt</dt>
            <dd><?= tableVal($u['phone_alternate']) ?></dd>
            <dt>Website</dt>
            <dd><?= tableVal($u['website']) ?></dd>
            <dt>Account Type</dt>
            <dd><?= tableVal($u['account_type']) ?></dd>
            <dt>Industry</dt>
            <dd><?= tableVal($u['industry']) ?></dd>
            <dt>Description</dt>
            <dd><?= tableVal($u['description']) ?></dd>
            <dt>ID Number</dt>
            <dd><?= tableVal($u['stic_identification_number_c']) ?></dd>
            <dt>ID Type</dt>
            <dd><?= tableVal($u['stic_identification_type_c']) ?></dd>
            <dt>Language</dt>
            <dd><?= tableVal($u['stic_language_c']) ?></dd>
            <dt>Address (ZIP)</dt>
            <dd><?= tableVal($u['billing_address_postalcode']) ?></dd>
            <dt>Address (Country)</dt>
            <dd><?= tableVal($u['billing_address_country']) ?></dd>
            <dt>Address (State)</dt>
            <dd><?= tableVal($u['billing_address_state']) ?></dd>
            <dt>Address (City)</dt>
            <dd><?= tableVal($u['billing_address_city']) ?></dd>
          <?php endif; ?>
          <dt>Email</dt>
          <dd><?= tableVal($u['email']) ?></dd>
        </div>
      <?php endif; ?>

      <?php if (!empty($data['relationships'])): ?>
        <h2>Relationships (<?= count($data['relationships']) ?>)</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Role</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data['relationships'] as $r): ?>
              <tr>
                <td><?= h($r['name'] ?? '') ?></td>
                <td><?= h($r['relationship_type'] ?? '') ?></td>
                <td><?= tableVal($r['start_date']) ?></td>
                <td><?= tableVal($r['end_date']) ?></td>
                <td><?= h($r['role'] ?? '') ?></td>
                <td>
                  <?php if (empty($r['end_date']) || $r['end_date'] === '0000-00-00'): ?>
                    <span class="badge badge-active">Active</span>
                  <?php else: ?>
                    <span class="badge badge-ended">Ended</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <span class="toggle" onclick="var el=document.getElementById('rawjson');el.classList.toggle('json-hidden');this.textContent=el.classList.contains('json-hidden')?'Show raw JSON':'Hide raw JSON'">Show raw JSON</span>
      <pre class="raw json-hidden" id="rawjson"><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>

    <?php elseif ($data && isset($data['error'])): ?>
      <div class="msg msg-error">Token exchange failed: <?= h($data['error']) ?>
        <?php if (!empty($data['message'])): ?><br><small><?= h($data['message']) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="links">
      <a href="index.php">Try again</a>
      <a href="<?= h(basename(__FILE__)) ?>">Reload</a>
    </div>

  </div>
</body>

</html>