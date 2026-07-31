<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Rate_Limiter
{
    public static function enforce(string $scope, string $identity, int $limit, int $windowSeconds): void
    {
        if ($limit < 1 || $windowSeconds < 1 || preg_match('/^[a-z0-9._|-]{1,96}$/i', $scope) !== 1) {
            throw new \VGTOmegaVault\SecurityException('Rate-limit validation failed.');
        }

        $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;
        $identityHash = VGT_Omega_Crypto::integrityHash($identity, 'rate-identity');
        $bucketKey = VGT_Omega_Crypto::integrityHash(
            $scope . '|' . $identityHash,
            'rate-bucket'
        );

        $count = VGT_Omega_DB::hitRateBucket($bucketKey, $windowStart);
        if ($count > $limit) {
            throw new \VGTOmegaVault\ValidationException(
                __('Too many requests. Retry later.', 'vgt-omega-vault'),
                429
            );
        }
    }
}
