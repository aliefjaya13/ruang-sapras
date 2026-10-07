<?php

function app_start_session(): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_set_cookie_params([
			"httponly" => true,
			"secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
			"samesite" => "Lax",
		]);
		session_start();
	}
}

function app_db(): mysqli
{
	ob_start();
	include __DIR__ . "/config.php";
	ob_end_clean();
	mysqli_set_charset($conn, "utf8mb4");

	return $conn;
}

function app_escape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function app_csrf_token(): string
{
	if (empty($_SESSION["csrf_token"])) {
		$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
	}

	return $_SESSION["csrf_token"];
}

function app_csrf_valid(): bool
{
	$submittedToken = is_string($_POST["csrf_token"] ?? null) ? $_POST["csrf_token"] : "";

	return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $submittedToken);
}

function app_current_user(mysqli $conn): ?array
{
	$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
	if (!$userId) {
		return null;
	}

	$statement = mysqli_prepare($conn, "SELECT id, nama, username, role FROM tblusers WHERE id = ? AND aktif = 1");
	if (!$statement) {
		return null;
	}

	mysqli_stmt_bind_param($statement, "i", $userId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$user = mysqli_fetch_assoc($result) ?: null;
	mysqli_stmt_close($statement);

	if (!$user) {
		$_SESSION = [];
		session_regenerate_id(true);
		return null;
	}

	$_SESSION["user_role"] = $user["role"];
	return $user;
}

function app_require_login(mysqli $conn): array
{
	$user = app_current_user($conn);
	if (!$user) {
		header("Location: index.php");
		exit();
	}

	return $user;
}

function app_require_roles(mysqli $conn, array $roles): array
{
	$user = app_require_login($conn);
	if (!in_array($user["role"], $roles, true)) {
		http_response_code(403);
		exit("Akses tidak diizinkan.");
	}

	return $user;
}