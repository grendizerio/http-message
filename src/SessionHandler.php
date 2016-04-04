<?php
namespace {

    /**
     * Return value of session_status() if sessions are disabled.
     * @since 5.4.0
     * @link http://php.net/manual/en/function.session-status.php
     */
    defined('PHP_SESSION_DISABLED') or define('PHP_SESSION_DISABLED', 0);
    /**
     * Return value of session_status() if sessions are enabled, but no session exists.
     * @since 5.4.0
     * @link http://php.net/manual/en/function.session-status.php
     */
    defined('PHP_SESSION_NONE') or define('PHP_SESSION_NONE', 1);
    /**
     * Return value of session_status() if sessions are enabled, and a session exists.
     * @since 5.4.0
     * @link http://php.net/manual/en/function.session-status.php
     */
    defined('PHP_SESSION_ACTIVE') or define('PHP_SESSION_ACTIVE', 2);

    if (!function_exists('session_status')) {
        /**
         * (PHP 5 >= 5.4.0)<br>
         * Returns the current session status
         * @link http://php.net/manual/en/function.session-status.php
         * @return int <b>PHP_SESSION_DISABLED</b> if sessions are disabled.
         * <b>PHP_SESSION_NONE</b> if sessions are enabled, but none exists.
         * <b>PHP_SESSION_ACTIVE</b> if sessions are enabled, and one exists.
         */
        function session_status()
        {
            return SessionHandler::sessionStatus();
        }
    }

    /**
     * <b>SessionHandlerInterface</b> is an interface which defines
     * a prototype for creating a custom session handler.
     * In order to pass a custom session handler to
     * session_set_save_handler() using its OOP invocation,
     * the class must implement this interface.
     * @link http://php.net/manual/en/class.sessionhandlerinterface.php
     * @since 5.4.0
     */
    interface SessionHandlerInterface
    {
        /**
         * Close the session
         * @link http://php.net/manual/en/sessionhandlerinterface.close.php
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function close();

        /**
         * Destroy a session
         * @link http://php.net/manual/en/sessionhandlerinterface.destroy.php
         * @param string $session_id The session ID being destroyed.
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function destroy($session_id);

        /**
         * Cleanup old sessions
         * @link http://php.net/manual/en/sessionhandlerinterface.gc.php
         * @param int $maxlifetime <p>
         * Sessions that have not updated for
         * the last maxlifetime seconds will be removed.
         * </p>
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function gc($maxlifetime);

        /**
         * Initialize session
         * @link http://php.net/manual/en/sessionhandlerinterface.open.php
         * @param string $save_path The path where to store/retrieve the session.
         * @param string $name The session name.
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function open($save_path, $name);


        /**
         * Read session data
         * @link http://php.net/manual/en/sessionhandlerinterface.read.php
         * @param string $session_id The session id to read data for.
         * @return string <p>
         * Returns an encoded string of the read data.
         * If nothing was read, it must return an empty string.
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function read($session_id);

        /**
         * Write session data
         * @link http://php.net/manual/en/sessionhandlerinterface.write.php
         * @param string $session_id The session id.
         * @param string $session_data <p>
         * The encoded session data. This data is the
         * result of the PHP internally encoding
         * the $_SESSION superglobal to a serialized
         * string and passing it as this parameter.
         * Please note sessions use an alternative serialization method.
         * </p>
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function write($session_id, $session_data);
    }

    /**
     * <b>SessionHandler</b> a special class that can
     * be used to expose the current internal PHP session
     * save handler by inheritance. There are six methods
     * which wrap the six internal session save handler
     * callbacks (open, close, read, write, destroy and gc).
     * By default, this class will wrap whatever internal
     * save handler is set as as defined by the
     * session.save_handler configuration directive which is usually
     * files by default. Other internal session save handlers are provided by
     * PHP extensions such as SQLite (as sqlite),
     * Memcache (as memcache), and Memcached (as memcached).
     * @link http://php.net/manual/en/class.reflectionzendextension.php
     * @since 5.4.0
     */
    class SessionHandler implements SessionHandlerInterface
    {
        /**
         * @var string
         */
        protected $savePath;

        /**
         * @var string
         */
        protected $name;

        /**
         * @var
         */
        protected $maxlifetime;

        public static function sessionStatus($status = null)
        {
            static $sessionStatus = PHP_SESSION_DISABLED;

            if (func_num_args()) {
                switch ($status) {
                    case PHP_SESSION_DISABLED:
                    case PHP_SESSION_NONE:
                    case PHP_SESSION_ACTIVE:
                        $sessionStatus = $status;
                        break;

                    case true:
                        $sessionStatus = PHP_SESSION_ACTIVE;
                        break;

                    case false:
                        $sessionStatus = PHP_SESSION_NONE;
                        break;
                }
            }

            return $sessionStatus;
        }

        /**
         * @link http://php.net/manual/en/function.session-set-save-handler.php
         * @since 5.3.0
         */
        public function __construct()
        {
            $this->maxlifetime = ini_get('session.gc_maxlifetime');

            session_set_save_handler(
                array($this, 'open'),
                array($this, 'close'),
                array($this, 'read'),
                array($this, 'write'),
                array($this, 'destroy'),
                array($this, 'gc')
            );

            register_shutdown_function(
                'session_write_close'
            );
        }

        /**
         * Close the session
         * @link http://php.net/manual/en/sessionhandler.close.php
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function close()
        {
            return true;
        }

        /**
         * Destroy a session
         * @link http://php.net/manual/en/sessionhandler.destroy.php
         * @param string $session_id The session ID being destroyed.
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function destroy($session_id)
        {
            $file = $this->target($session_id);
            return file_exists($file) && !unlink($file);
        }

        /**
         * Cleanup old sessions
         * @link http://php.net/manual/en/sessionhandler.gc.php
         * @param int $maxlifetime <p>
         * Sessions that have not updated for
         * the last maxlifetime seconds will be removed.
         * </p>
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function gc($maxlifetime)
        {
            foreach (glob($this->target('*')) as $file) {
                file_exists($file) && unlink($file);
            }

            return true;
        }

        /**
         * Initialize session
         * @link http://php.net/manual/en/sessionhandler.open.php
         * @param string $save_path The path where to store/retrieve the session.
         * @param string $name The session id.
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function open($save_path, $name)
        {
            $this->savePath = $save_path;
            $this->name = $name;

            $session_id = session_id();

            $stared = version_compare(phpversion(), '5.4.0', '>=')
                ? session_status() === PHP_SESSION_ACTIVE
                : !empty($session_id);

            $stared && static::sessionStatus(!empty($_SESSION));

            if (empty($session_id)) {
                $session_id = md5(uniqid('ghm1.x'));
                session_id($session_id);
                setcookie(session_name(), $session_id, 0, '/', '.');
            }

            if (!is_dir($this->savePath)) {
                mkdir($this->savePath, 0777);
            }

            return true;
        }


        /**
         * Read session data
         * @link http://php.net/manual/en/sessionhandler.read.php
         * @param string $session_id The session id to read data for.
         * @return string <p>
         * Returns an encoded string of the read data.
         * If nothing was read, it must return an empty string.
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function read($session_id)
        {
            return (string)@file_get_contents($this->target($session_id));
        }

        /**
         * Write session data
         * @link http://php.net/manual/en/sessionhandler.write.php
         * @param string $session_id The session id.
         * @param string $session_data <p>
         * The encoded session data. This data is the
         * result of the PHP internally encoding
         * the $_SESSION superglobal to a serialized
         * string and passing it as this parameter.
         * Please note sessions use an alternative serialization method.
         * </p>
         * @return bool <p>
         * The return value (usually TRUE on success, FALSE on failure).
         * Note this value is returned internally to PHP for processing.
         * </p>
         * @since 5.4.0
         */
        public function write($session_id, $session_data)
        {
            return file_put_contents($this->target($session_id), $session_data);
        }

        protected function target($session_id)
        {
            return $this->savePath . '/sess_' . $this->maxlifetime . '_' . $session_id;
        }
    }
}
