<?php

namespace Grendizer\HttpMessage;

class ResponseHeaderBag extends HeaderBag
{
    const COOKIES_FLAT = 'flat';
    const COOKIES_ARRAY = 'array';

    /**
     * @var array
     */
    protected $cookies = array();

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $cookies = '';

        foreach ($this->getCookies() as $cookie) {
            $cookies .= 'Set-Cookie: '.$cookie."\r\n";
        }

        return parent::__toString().$cookies;
    }

    /**
     * Sets a cookie.
     *
     * @param Cookie $cookie
     */
    public function setCookie(Cookie $cookie)
    {
        $this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
    }

    /**
     * Removes a cookie from the array, but does not unset it in the browser.
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public function removeCookie($name, $path = '/', $domain = null)
    {
        if (null === $path) {
            $path = '/';
        }

        unset($this->cookies[$domain][$path][$name]);

        if (empty($this->cookies[$domain][$path])) {
            unset($this->cookies[$domain][$path]);

            if (empty($this->cookies[$domain])) {
                unset($this->cookies[$domain]);
            }
        }
    }

    /**
     * Returns an array with all cookies.
     *
     * @param string $format
     *
     * @throws \InvalidArgumentException When the $format is invalid
     *
     * @return array
     */
    public function getCookies($format = self::COOKIES_FLAT)
    {
        if (!in_array($format, array(self::COOKIES_FLAT, self::COOKIES_ARRAY))) {
            throw new \InvalidArgumentException(sprintf('Format "%s" invalid (%s).', $format, implode(', ', array(self::COOKIES_FLAT, self::COOKIES_ARRAY))));
        }

        if (self::COOKIES_ARRAY === $format) {
            return $this->cookies;
        }

        $flattenedCookies = array();
        foreach ($this->cookies as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattenedCookies[] = $cookie;
                }
            }
        }

        return $flattenedCookies;
    }

    /**
     * Clears a cookie in the browser.
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httpOnly
     */
    public function clearCookie($name, $path = '/', $domain = null, $secure = false, $httpOnly = true)
    {
        $this->setCookie(new Cookie($name, null, 1, $path, $domain, $secure, $httpOnly));
    }
}
