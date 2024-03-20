<?php

    namespace Coco\validate;

    class Validate
    {
        const SCENE_ADD  = 'add';
        const SCENE_EDIT = 'edit';

        private static array $extendsCallback = [];

        protected array $rule     = [];
        private array   $errorMsg = [];
        private bool    $isPassed = true;
        private string  $scene    = '';
        private array   $data     = [];

        private array $alias = [
            '>'    => 'gt',
            '>='   => 'egt',
            '<'    => 'lt',
            '<='   => 'elt',
            '='    => 'eq',
            'same' => 'eq',
        ];

        private array $regex = [
            'alpha'           => '/^[A-Za-z]+$/',
            'alphaNum'        => '/^[A-Za-z0-9]+$/',
            'alphaDash'       => '/^[A-Za-z0-9\-\_]+$/',
            'chinese'         => '/^[\x{4e00}-\x{9fa5}]+$/u',
            'chineseAlpha'    => '/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u',
            'chineseAlphaNum' => '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u',
            'chineseDash'     => '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9\_\-]+$/u',
            'mobile'          => '/^1[3-9][0-9]\d{8}$/',
            'idCard'          => '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{2}$)/',
            'zip'             => '/\d{6}/',
        ];

        public static function extends($name, callable $callback): void
        {
            static::$extendsCallback[$name] = $callback;
        }

        public function addErrorMsg($field, $msg): static
        {
            $this->errorMsg[$field][] = $msg;

            return $this;
        }

        public function getErrorMsg($field): ?string
        {
            return $this->errorMsg[$field] ?? null;
        }

        public function getAllErrorMsg(): array
        {
            return $this->errorMsg;
        }

        public function setScene(string $scene): static
        {
            $this->scene = $scene;

            return $this;
        }

        public function implortRule(array $rules): static
        {
            foreach ($rules as $k => $v)
            {
                $this->addRule($k, $v);
            }

            return $this;
        }

        public function addRule(string $scene, array $rule): static
        {
            $this->rule[$scene] = $rule;

            return $this;
        }

        public function setData(array $data): static
        {
            $this->data = $data;

            return $this;
        }


        /**
         * ---------------------------------------------------------------
         * ---------------------------------------------------------------
         */

        /**
         * @return bool
         * @throws \Exception
         */
        public function check(): bool
        {
            if (!isset($this->rule[$this->scene]))
            {
                throw new \Exception('未定义的场景: ' . $this->scene);
            }

            foreach ($this->rule[$this->scene] as $field => $rules)
            {
                $value     = $this->data[$field] ?? '';
                $isRequire = isset($rules['require']);

                foreach ($rules as $rule => $msg)
                {
                    $t = explode(':', $rule);

                    $method = $t[0];

                    if (count($t) > 2)
                    {
                        array_shift($t);
                        $args = implode(':', $t);
                    }
                    else
                    {
                        $args = $t[1] ?? '';
                    }

                    if (isset($this->alias[$method]))
                    {
                        $method = $this->alias[$method];
                    }

                    if (isset($this->regex[$method]))
                    {
                        $args   = $this->regex[$method];
                        $method = 'regex';
                    }

                    if (isset(static::$extendsCallback[$method]))
                    {
                        $callback = static::$extendsCallback[$method];
                    }
                    else
                    {
                        $callback = [
                            $this,
                            $method,
                        ];
                    }

                    $isPassed = $callback($args, $value, $field, $this->data, $isRequire);

                    if (!$isPassed)
                    {
                        $this->addErrorMsg($field, $msg);
                        $this->isPassed = false;
                    }
                }
            }

            return $this->isPassed;
        }

        /**
         * @param mixed $value
         * @param array $rules
         *
         * @return bool
         */
        public static function verifyBatch(mixed $value, array $rules): bool
        {
            foreach ($rules as $k => $rule)
            {
                if (!static::verify($value, $rule))
                {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param mixed  $value
         * @param string $rule
         *
         * @return bool
         */
        public static function verify(mixed $value, string $rule): bool
        {
            $ins = new static();

            $t = explode(':', $rule);

            $method = $t[0];

            if (count($t) > 2)
            {
                array_shift($t);
                $args = implode(':', $t);
            }
            else
            {
                $args = $t[1] ?? '';
            }

            if (isset($ins->alias[$method]))
            {
                $method = $ins->alias[$method];
            }

            if (isset($ins->regex[$method]))
            {
                $args   = $ins->regex[$method];
                $method = 'regex';
            }

            if (isset(static::$extendsCallback[$method]))
            {
                $callback = static::$extendsCallback[$method];
            }
            else
            {
                $callback = [
                    $ins,
                    $method,
                ];
            }

            return $callback($args, $value, '__', $ins->data, true);
        }

        /**
         * ---------------------------------------------------------------
         * ---------------------------------------------------------------
         */

        /**
         * 'require' => ''
         *
         * @param string $args
         * @param mixed  $value
         * @param mixed  $field
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function require(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return !empty($data[$field]);
        }

        /**
         * 'in:2,3,4' => ''
         *
         * @param string $args
         * @param mixed  $value
         * @param mixed  $field
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function in(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            $d = explode(',', $args);

            if (!count($d))
            {
                return false;
            }

            return in_array($value, $d);
        }

        /**
         * 'notIn:2,3,4' => ''
         *
         * @param string $args
         * @param mixed  $value
         * @param mixed  $field
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function notIn(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            $d = explode(',', $args);
            if (!count($d))
            {
                return false;
            }

            return !in_array($value, $d);
        }

        /**
         * 'between:2,4' => ''
         * 'between:,4' => ''
         * 'between:2,' => ''
         *
         * @param string $args
         * @param mixed  $value
         * @param mixed  $field
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function between(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            $d = explode(',', $args);

            if (empty($d[0]))
            {
                $d[0] = PHP_INT_MIN;
            }

            if (empty($d[1]))
            {
                $d[1] = PHP_INT_MAX;
            }

            return ($value >= $d[0]) and ($value <= $d[1]);
        }

        /**
         * 'notBetween:2,4' => ''
         * 'notBetween:,4' => ''
         * 'notBetween:2,' => ''
         *
         * @param string $args
         * @param mixed  $value
         * @param mixed  $field
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function notBetween(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            $d = explode(',', $args);

            if (empty($d[0]))
            {
                $d[0] = PHP_INT_MIN;
            }

            if (empty($d[1]))
            {
                $d[1] = PHP_INT_MAX;
            }

            return ($value < $d[0]) or ($value > $d[1]);
        }

        /**
         * 'lengthRange:2,4' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function lengthRange(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            $length = mb_strlen((string)$value);
            $d      = explode(',', $args);

            if (empty($d[0]))
            {
                $d[0] = PHP_INT_MIN;
            }
            if (!isset($d[1]) || !$d[1])
            {
                $d[1] = PHP_INT_MAX;
            }

            return ($length >= $d[0]) and ($length <= $d[1]);
        }

        /**
         * 'startWith:aaa' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function startWith(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return str_starts_with($value, $args);
        }

        /**
         * 'endWith:aaa' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function endWith(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return str_ends_with($value, $args);
        }

        /**
         * 'contain:aaa' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function contain(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return str_contains($value, $args);
        }

        /**
         * 'sameField:pwd_confirm' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function sameField(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value == $data[$args]);
        }

        /**
         * 'max:2' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function max(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            $d = explode(',', $args);

            return ($value <= $d[0]);
        }

        /**
         * 'min:7' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function min(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            $d = explode(',', $args);

            return ($value >= $d[0]);
        }

        /**
         * 'number' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function number(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return is_numeric($value);
        }

        /**
         * 'string' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function string(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return is_string($value);
        }

        /**
         * 'float' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function float(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return is_float($value);
        }

        /**
         * 'int' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function int(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return is_int($value);
        }

        /**
         * 'array' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function array(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return is_array($value);
        }

        /**
         * 'bool' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function bool(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return in_array($value, [
                true,
                false,
                0,
                1,
                '0',
                '1',
            ], true);
        }

        /**
         * 'accepted' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function accepted(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return in_array($value, [
                '1',
                '1',
                'on',
                'yes',
            ]);
        }

        /**
         * 'denied' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function denied(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return in_array($value, [
                '0',
                'off',
                'no',
            ]);
        }

        /**
         * 'isDate' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function isDate(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            if (!is_string($value))
            {
                return false;
            }

            return false !== strtotime($value);
        }

        /**
         * 'afterDate:2012-9-16 14:25:55' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function afterDate(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            if (!is_string($value))
            {
                return false;
            }

            return strtotime($value) >= strtotime($args);
        }

        /**
         * 'beforeDate:2012-9-16 14:25:55' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function beforeDate(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }
            if (!is_string($value))
            {
                return false;
            }

            return strtotime($value) <= strtotime($args);
        }

        /**
         * 'gt:6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function gt(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value > $args);
        }

        /**
         * 'egt:6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function egt(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value >= $args);
        }

        /**
         * 'lt:6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function lt(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value < $args);
        }

        /**
         * 'elt:6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function elt(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value <= $args);
        }

        /**
         * 'eq:6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function eq(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            return ($value == $args);
        }

        /**
         * 'availableUrl' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function availableUrl(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return checkdnsrr($value);
        }

        /**
         * 'email' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function email(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return filter_var($value, FILTER_VALIDATE_EMAIL);
        }

        /**
         * 'ipv4' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function ipv4(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }

        /**
         * 'ipv6' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function ipv6(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        /**
         * 'regex:#^\d+$#' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function regex(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }

            return preg_match($args, $value);
        }

        /**
         * 'fileExt:jpg,png' => ''
         *
         * @param string $args
         * @param mixed  $field
         * @param mixed  $value
         * @param array  $data
         * @param bool   $isRequire
         *
         * @return bool
         */
        public function fileExt(string $args, mixed $value, mixed $field, array $data, bool $isRequire): bool
        {
            if (!$isRequire && empty($value))
            {
                return true;
            }

            if (!is_string($value))
            {
                return false;
            }
            $d = explode(',', strtolower($args));

            $ext = pathinfo($value, PATHINFO_EXTENSION);

            return in_array(strtolower($ext), $d);
        }
    }
