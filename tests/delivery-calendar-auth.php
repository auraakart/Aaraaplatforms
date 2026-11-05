<?php
/**
 * Isolated authorization tests for the delivery calendar endpoint.
 * Does not require a running WordPress installation or customer records.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
final class WP_REST_Request {
    private array $params;
    public function __construct(array $params) { $this->params = $params; }
    public function get_param(string $key) { return $this->params[$key] ?? null; }
}
final class WP_Error {
    private string $code;
    private array $data;
    public function __construct(string $code, string $message, array $data = []) {
        $this->code = $code;
        $this->data = $data;
    }
    public function get_error_code(): string { return $this->code; }
    public function get_error_data(): array { return $this->data; }
}
function __(string $text, string $domain = ''): string { return $text; }
function absint($value): int { return abs((int)$value); }
$GLOBALS['auth_user'] = 0;
$GLOBALS['can_list_users'] = false;
function is_user_logged_in(): bool { return (int)$GLOBALS['auth_user'] > 0; }
function get_current_user_id(): int { return (int)$GLOBALS['auth_user']; }
function current_user_can(string $cap): bool {
    return $cap === 'list_users' && (bool)$GLOBALS['can_list_users'];
}
require_once dirname(__DIR__) . '/wp-content/plugins/aaraa-white-label-admin/includes/class-delivery-calendar-api.php';
$api = new Aaraa\Admin\Delivery_Calendar_API();
$check = static function (int $authUser, bool $admin, int $requested, bool $allowed, int $errorStatus = 0) use ($api): void {
    $GLOBALS['auth_user'] = $authUser;
    $GLOBALS['can_list_users'] = $admin;
    $result = $api->can_read_calendar(new WP_REST_Request(['customer_id' => $requested]));
    $passed = $allowed ? $result === true
        : $result instanceof WP_Error && ($result->get_error_data()['status'] ?? null) === $errorStatus;
    if (!$passed) {
        fwrite(STDERR, "Unexpected calendar authorization decision for auth={$authUser}, customer={$requested}\n");
        exit(1);
    }
};
$check(0, false, 2, false, 401);  // Anonymous user.
$check(7, false, 7, true);         // Own calendar.
$check(7, false, 8, false, 403);  // Cross-customer IDOR rejected.
$check(7, false, 0, false, 403);  // Missing customer rejected.
$check(7, true, 8, true);          // Privileged manager can access.
echo "Delivery calendar authorization checks passed.\n";
