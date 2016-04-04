<?php

namespace Grendizer\HttpMessage;

class Session implements SessionInterface
{
    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var bool
     */
    protected $closed = false;

    /**
     * @var \SessionHandler|\SessionHandlerInterface
     */
    protected $handler;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var string
     */
    protected $scope;

    /**
     * Session constructor.
     *
     * @param \SessionHandlerInterface $handler
     * @param string $scope
     */
    public function __construct(\SessionHandlerInterface $handler = null, $scope = '_ghm1x_')
    {
        $this->setHandler($handler ?: new \SessionHandler());
        $this->scope = $scope;
    }

    /**
     * Set session handler
     *
     * @param \SessionHandlerInterface $handler
     */
    public function setHandler(\SessionHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @inheritdoc
     */
    public function start()
    {
        if ($this->started) {
            return true;
        }

        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \RuntimeException('Failed to start the session: already started by PHP.');
        }

        if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
        }

        // ok to try and start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        $this->loadSession();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        if (method_exists($this->handler, 'getId')) {
            return $this->handler->getId();
        }

        return session_id();
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        // 活动状态禁止修改
        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \LogicException('Cannot change the ID of an active session');
        }

        if (method_exists($this->handler, 'setId')) {
            $this->handler->setId($id);
        } else {
            session_id($id);
        }
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return session_name();
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        // 活动状态禁止修改
        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \LogicException('Cannot change the ID of an active session');
        }

        session_name($name);
    }

    /**
     * @inheritdoc
     */
    public function invalidate($lifetime = null)
    {
        $this->attributes = array();
        return $this->migrate(true, $lifetime);
    }

    /**
     * @inheritdoc
     */
    public function migrate($destroy = false, $lifetime = null)
    {
        // Cannot regenerate the session ID for non-active sessions.
        if (\PHP_SESSION_ACTIVE !== session_status()) {
            return false;
        }

        if (null !== $lifetime) {
            ini_set('session.cookie_lifetime', $lifetime);
        }

        $isRegenerated = session_regenerate_id($destroy);

        // The reference to $_SESSION in session bags is lost in PHP7 and we need to re-create it.
        // @see https://bugs.php.net/bug.php?id=70013
        $this->loadSession();

        return $isRegenerated;
    }

    /**
     * @inheritdoc
     */
    public function save()
    {
        session_write_close();
        $this->closed = true;
        $this->started = false;
    }

    /**
     * @inheritdoc
     */
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @inheritdoc
     */
    public function get($name, $default = null)
    {
        return $this->has($name) ? $this->attributes[$name] : $default;
    }

    /**
     * @inheritdoc
     */
    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @inheritdoc
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * @inheritdoc
     */
    public function replace(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @inheritdoc
     */
    public function remove($name)
    {
        $retval = $this->get($name);
        unset($this->attributes[$name]);
        return $retval;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->attributes = array();
        $_SESSION = array();//????
        $this->loadSession();
    }

    /**
     * @inheritdoc
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * @inheritdoc
     */
    protected function loadSession(array &$session = null)
    {
        if (null === $session) {
            $session = &$_SESSION;
        }

        if (!isset($session[$this->scope])) {
            $session[$this->scope] = array();
        }

        $this->attributes = &$session[$this->scope];
        $this->started = true;
        $this->closed = false;
    }
}
