<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/aaraa-tenant-routing.php';
$cases = array(
    array('example.com', null),
    array('www.example.com', null),
    array('api.example.com', null),
    array('milk.example.com', 'milk'),
    array('MiLk.ExAmPlE.CoM:443', 'milk'),
    array('example.com.evil.com', false),
    array('milk.example.com.evil.org', false),
    array('milk.other.com', false),
    array('foo.bar.example.com', false),
    array('milk..example.com', false),
    array('-bad.example.com', false),
    array('bad-.example.com', false),
    array('evil@example.com', false),
    array('example.com:99999', false),
    array('', false),
    array('milk.example.com:443/path', false),
    array('milk.example.com.', false),
);
foreach ($cases as $case) {
    $result = aaraa_tenant_from_host($case[0], 'example.com');
    if ($result !== $case[1]) {
        fwrite(STDERR, 'FAIL tenant host: ' . $case[0] . PHP_EOL);
        exit(1);
    }
}
if (aaraa_tenant_from_host('milk.example.com', '') !== false) {
    fwrite(STDERR, "Missing base domain must reject all hosts\n");
    exit(1);
}
echo "Tenant hostname routing tests passed.\n";
