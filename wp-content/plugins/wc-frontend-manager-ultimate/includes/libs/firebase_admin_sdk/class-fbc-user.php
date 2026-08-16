<?php

if (! defined('ABSPATH')) {
    exit;
    // Exit if accessed directly
}

class FBC_User
{

    private $agent;

    private $info = [];


    function __construct()
    {
        $this->agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        $this->get_browser();
        $this->get_OS();

    }//end __construct()


    /**
     * Get browser info
     *
     * @return string
     */
    function get_browser()
    {
        $browsers = [
            'Edge'              => '/Edg\/(.+)/i',
            'Firefox'           => '/Firefox\/(.+)/i',
            'Internet Explorer' => '/MSIE\s(.+)/i',
            'Chrome'            => '/Chrome\/(.+)/i',
            'MAXTHON'           => '/Maxthon\/(.+)/i',
            'Opera'             => '/Opera\/(.+)/i',
            'Navigator'         => '/Navigator\/(.+)/i',
        ];

        $this->info['Browser'] = 'N/A';
        $this->info['Version'] = 'N/A';

        // Find browser — set defaults first, then overwrite on first match
        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $this->agent, $matches)) {
                $this->info['Browser'] = $name;
                $this->info['Version'] = isset($matches[1]) ? trim($matches[1]) : 'N/A';
                break;
            }
        }

        return $this->info['Browser'];

    }//end get_browser()


    /**
     * Get OS info
     *
     * @return string
     */
    function get_OS()
    {
        $this->info['OS'] = 'N/A';

        $OS = [
            '/windows nt 10\.0/i'   => 'Windows 10/11',
            '/windows nt 6\.2/i'    => 'Windows 8',
            '/windows nt 6\.1/i'    => 'Windows 7',
            '/windows nt 6\.0/i'    => 'Windows Vista',
            '/windows nt 5\.2/i'    => 'Windows Server 2003/XP x64',
            '/windows nt 5\.1/i'    => 'Windows XP',
            '/windows xp/i'         => 'Windows XP',
            '/windows nt 5\.0/i'    => 'Windows 2000',
            '/windows me/i'         => 'Windows ME',
            '/win98/i'              => 'Windows 98',
            '/win95/i'              => 'Windows 95',
            '/win16/i'              => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i'        => 'Mac OS 9',
            '/ubuntu/i'             => 'Ubuntu',
            '/linux/i'              => 'Linux',
            '/iphone/i'             => 'iPhone',
            '/ipod/i'               => 'iPod',
            '/ipad/i'               => 'iPad',
            '/android/i'            => 'Android',
            '/blackberry/i'         => 'BlackBerry',
            '/webos/i'              => 'Mobile',
        ];

        foreach ($OS as $regex => $v) {
            if (preg_match($regex, $this->agent)) {
                $this->info['OS'] = $v;
                break; // Stop at first match
            }
        }

        return $this->info['OS'];

    }//end get_OS()




    /**
     * Show user info
     *
     * @return mixed
     */
    function info($switch)
    {
        switch (strtolower($switch)) {
            case 'browser':
                return $this->info['Browser'];
            case 'os':
                return $this->info['OS'];
            case 'version':
                return $this->info['Version'];
            case 'all':
                return [
                    $this->info['Version'],
                    $this->info['OS'],
                    $this->info['Browser'],
                ];
            default:
                return 'N/A';
        }//end switch

    }//end info()


}//end class
