<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaCertification
{
    const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    const CODE_READY = 'CODE_READY';
    const MOCK_VERIFIED = 'MOCK_VERIFIED';
    const LOCAL_RUNTIME_VERIFIED = 'LOCAL_RUNTIME_VERIFIED';
    const MISA_TEST_CONNECTED = 'MISA_TEST_CONNECTED';
    const MISA_END_TO_END_VERIFIED = 'MISA_END_TO_END_VERIFIED';
    const PRODUCTION_PILOT_READY = 'PRODUCTION_PILOT_READY';
    const PRODUCTION_CERTIFIED = 'PRODUCTION_CERTIFIED';

    public static function allowed() { return [self::NOT_IMPLEMENTED, self::CODE_READY, self::MOCK_VERIFIED, self::LOCAL_RUNTIME_VERIFIED, self::MISA_TEST_CONNECTED, self::MISA_END_TO_END_VERIFIED, self::PRODUCTION_PILOT_READY, self::PRODUCTION_CERTIFIED]; }
    public static function canUseExternal($level) { return in_array((string) $level, [self::MISA_TEST_CONNECTED, self::MISA_END_TO_END_VERIFIED, self::PRODUCTION_PILOT_READY, self::PRODUCTION_CERTIFIED], true); }
}

