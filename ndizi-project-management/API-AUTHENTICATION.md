# Ndizi Project Management — REST API Authentication

Ndizi Project Management exposes a robust set of REST API endpoints under the `/wp-json/ndizi/v1` namespace. External applications, browser extensions, and developer scripts can interact with these endpoints securely using WordPress **Application Passwords**.

---

## 1. Creating an Application Password

1. Log in to your WordPress Admin dashboard.
2. Navigate to **Users** > **Profile** (or edit any user account with appropriate Ndizi permissions).
3. Scroll down to the **Application Passwords** section.
4. Enter an application name (e.g., `Chrome Extension` or `Python Script`) and click **Add New Application Password**.
5. Copy the generated password. *Note: You will not be able to see it again after navigating away.*

---

## 2. Authenticating Requests

The REST API authenticates using the standard HTTP **Basic Authentication** scheme. The username is your WordPress username (or email), and the password is the generated Application Password (space-separated characters are valid as-is).

Create a Basic Auth header by encoding the credentials:
`Authorization: Basic base64(username:application_password)`

### Example cURL Request

To fetch all active projects:
```bash
curl -X GET "https://your-wordpress-site.local/wp-json/ndizi/v1/projects" \
  -H "Authorization: Basic Base64EncodedCredentials" \
  -H "Content-Type: application/json"
```

### Example Node.js / JavaScript Fetch
```javascript
const username = 'admin';
const appPassword = 'xxxx xxxx xxxx xxxx xxxx xxxx'; // The 24-character generated password
const credentials = btoa(`${username}:${appPassword}`);

fetch('https://your-wordpress-site.local/wp-json/ndizi/v1/time/active', {
    method: 'GET',
    headers: {
        'Authorization': `Basic ${credentials}`,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log('Active Timer:', data))
.catch(error => console.error('Error:', error));
```

### Example PHP Request
```php
$username     = 'admin';
$app_password = 'xxxx xxxx xxxx xxxx xxxx xxxx';
$site_url     = 'https://your-wordpress-site.local';

$response = wp_remote_get(
	$site_url . '/wp-json/ndizi/v1/projects',
	array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( "$username:$app_password" ),
		),
	)
);

if ( ! is_wp_error( $response ) ) {
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	print_r( $data );
}
```

---

## 3. Chrome Extension — Credential Storage Notes

The Chrome extension stores the Base64-encoded Basic Auth header in `chrome.storage.local`
alongside the WordPress site URL and username. Be aware of the following:

- **Credentials are unencrypted.** `chrome.storage.local` is not encrypted on disk;
  any other extension granted `storage` access (or physical access to the Chrome profile
  directory) can read stored credentials.
- **Use dedicated, revocable Application Passwords.** Never store your main WordPress
  password. Generate a specific Application Password for the extension (e.g.,
  `Chrome Extension`) so you can revoke it independently without changing your login
  credentials.
- **Rotate periodically.** Application Passwords have no expiry by default; rotate them
  if you suspect exposure or remove the extension from a machine you no longer control.

**Keeping secrets out of the DB (for self-hosted installs):** If your site uses the
REST API programmatically, you can define Stripe/Google secrets as PHP constants in
`wp-config.php` instead of the database:

```php
define( 'NDIZI_STRIPE_SECRET_KEY', 'sk_live_...' );
define( 'NDIZI_GOOGLE_CLIENT_SECRET', '...' );
```

Ndizi will check for these constants before falling back to `wp_options`, so secrets
never appear in database exports or option-table dumps.

---

## 4. Core Ndizi Endpoint Cheat Sheet

All routes are relative to `https://your-wordpress-site.local/wp-json/ndizi/v1`:

* **GET `/projects`** — List active projects.
* **GET `/tasks`** — List tasks. Pass optional query parameter `?project_id=123`.
* **GET `/time/active`** — Retrieve running timer for the authenticated user.
* **POST `/time/start`** — Start a timer. Requires JSON body: `{"project_id": 123, "task_id": 456, "description": "Writing code"}`.
* **POST `/time/stop`** — Stop running timer.
* **POST `/time/log`** — Log manual entry. Requires body: `{"project_id": 123, "duration": 3600}`.
