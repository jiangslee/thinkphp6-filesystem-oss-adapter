<?php

if ( ! function_exists('gmt_iso8601')) {
    /**
     * @param int $time
     *
     * @return string
     * @throws Exception
     * @author liuxiaolong
     */
    function gmt_iso8601(int $time): string
    {
        return (new \DateTime('', new \DateTimeZone('UTC')))->setTimestamp($time)->format('Y-m-d\TH:i:s\Z');
    }
}