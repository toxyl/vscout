<?php
    class AccessControl
    {
        static public function get_acl($route)
        {
            return ReflectionUtils::get_meta_info(Routes::$class, $route, 'ACL', [ ]);
        }

        static public function access_granted()
        {
            $acl = self::get_acl(Router::$route);
            $granted = count($acl) > 0 && in_array(Router::$mode, $acl);

            if (!$granted)
                die("Access denied!"); // hell no, you're not going any further

            return true;
        }
    }
