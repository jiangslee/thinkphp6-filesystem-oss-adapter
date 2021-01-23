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
        $expiration = (new DateTime(date("c", $time)))->format(DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }
}