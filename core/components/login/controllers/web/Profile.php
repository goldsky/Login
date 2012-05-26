<?php
/**
 * Login
 *
 * Copyright 2010 by Shaun McCormick <shaun+login@modx.com>
 *
 * Login is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Login is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Login; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package login
 */
/**
 * Displays the profile of a specific user
 *
 * @package login
 * @subpackage controllers
 */
class LoginProfileController extends LoginController {
    /** @var modUser $user */
    public $user;
    /** @var modUserProfile $profile */
    public $profile;

    public function initialize() {
        $this->setDefaultProperties(array(
            'prefix' => '',
            'user' => false,
            'useExtended' => true,
            'extendedTpls' => ''
        ));
        $this->modx->lexicon->load('login:profile');
    }

    /**
     * Process the controller
     * @return string
     */
    public function process() {
        if (!$this->getUser()) {
            return '';
        }
        if (!$this->getProfile()) {
            return '';
        }

        $this->setToPlaceholders();
        return '';
    }

    /**
     * Set the user data to placeholders
     *
     * @return array
     */
    public function setToPlaceholders() {
        $placeholders = array_merge($this->profile->toArray(),$this->user->toArray());
        if ($extendedTpls = $this->getProperty('extendedTpls')) {
            $extended = $this->extendedTpls($extendedTpls, $placeholders['extended']);
        } else {
            $extended = $this->getExtended();
        }
        $placeholders = array_merge($extended,$placeholders);
        $placeholders = $this->removePasswordPlaceholders($placeholders);
        $this->modx->toPlaceholders($placeholders,$this->getProperty('prefix','','isset'),'');
        return $placeholders;
    }

    /**
     * Remove the password fields from the outputted placeholders
     * @param array $placeholders
     * @return array
     */
    public function removePasswordPlaceholders(array $placeholders = array()) {
        unset($placeholders['password'],$placeholders['cachepwd']);
        return $placeholders;
    }

    /**
     * Return the content of numeric keys into the chunk to be loop according to
     * the sum of the rows
     * @param string $tpls {parent-key:chunk[,parent-key:chunk]} pairings
     * @param array $extended extended contents
     * @return array parsed extended contents
     */
    public function extendedTpls($tpls, array $extended) {
        $templates = json_decode($tpls,1);
        $output = array();
        foreach ($extended as $k => $v) {
            if (is_array($v)) {
                $rec = $this->_recursiveNumericChild($v);
                if ($rec['count'] > 0) {
                    $rows = array();
                    if ($flip = $this->_flipNumericChild($v, $k)) {
                        for ($i = 0; $i < $rec['count']; $i++) {
                            // $output[$k] = $flip;
                            $rows[] = $this->login->getChunk($templates[$k], $flip[$i], $this->getProperty('tplType', 'modChunk'));
                        }
                    }
                    $output[$k] = @implode("\n", $rows);
                } else {
                    $output[$k] = $v;
                }
            } else {
                $output[$k] = $v;
            }
        }
        return $output;
    }

    /**
     * Flip the numeric keys as the parent key of the same extended sets
     * @param array $array extended array
     * @param string $parentKey parent key's name to be glued back as the placeholder's prefix
     * @param string $separator placeholder names' separator
     * @return array flipped array
     */
    private function _flipNumericChild(array $array, $parentKey, $separator='.') {
        $flip = $ar = array();
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $ar[] = $this->_implodePhs($v, $k);
            } else {
                $ar[][$k] = $v;
            }
        }
        foreach ($ar as $v) {
            $exp = array(); $imp = '';
            foreach ($v as $a => $b) {
                $exp = @explode($separator, $a);
                $index = array_pop($exp);
                $imp = @implode($separator, $exp);
                $key = !empty($imp) ? $parentKey . $separator . $imp : $parentKey;
                $flip[$index][$key] = $b;
            }
        }

        return $flip;
    }

    /**
     * Helper method to detect the existance of numeric keys
     * @param array $tree raw array
     * @param int $depth get the depth of multi dimension array
     * @return array depth & count of numeric keys in the extended profile
     */
    private function _recursiveNumericChild(array $tree, $depth = 0) {
        $rec = array();
        foreach ($tree as $k => $v) {
            if (is_array($v)) {
                return $this->_recursiveNumericChild($v, $depth+1);
            } else {
                $rec['depth'] = $depth;
                /* this below detects multiple field names based on numeric array */
                $rec['count'] = is_numeric($k) ? count($tree) : 0;
                return $rec;
            }
        }
    }

    /**
     * Get extended fields for a user
     * @return array
     */
    public function getExtended() {
        $extended = array();
        if ($this->getProperty('useExtended',true,'isset')) {
            $getExtended = $this->profile->get('extended');
            $ext = array();
            foreach ($getExtended as $k => $v) {
                if (is_array($v)) {
                    $ext[] = $this->_implodePhs($v, $k);
                } else {
                    $ext[][$k] = $v;
                }
            }
            foreach ($ext as $v) {
                foreach ($v as $a => $b) {
                    $extended[$a] = $b;
                }
            }
        }
        return (array) $extended;
    }

    /**
     * Merge multi dimensional associative arrays with separator
     * @param array $array raw associative array
     * @param string $keyName parent key of this array
     * @param string $separator separator between the merged keys
     * @param array $holder to hold temporary array results
     * @return array one level array
     */
    private function _implodePhs(array $array, $keyName = null, $separator = '.', array $holder = array()) {
        $phs = !empty($holder) ? $holder : array();
        foreach ($array as $k => $v) {
            $key = !empty($keyName) ? $keyName . $separator . $k : $k;
            if (is_array($v)) {
                $phs = $this->_implodePhs($v, $key, $separator, $phs);
            } else {
                $phs[$key] = $v;
            }
        }
        return $phs;
    }

    /**
     * Get the profile for the user
     *
     * @return bool|modUserProfile
     */
    public function getProfile() {
        $this->profile = $this->user->getOne('Profile');
        if (empty($this->profile)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'Could not find profile for user: '.$this->user->get('username'));
            return false;
        }
        return $this->profile;
    }

    /**
     * Get the specified or active user
     * @return boolean|modUser
     */
    public function getUser() {
        $user = $this->getProperty('user',false,'isset');

        /* verify authenticated status if no user specified */
        if (empty($user) && !$this->modx->user->hasSessionContext($this->modx->context->get('key'))) {
            $this->user = false;
        }
        /* specifying a specific user, so try and get it */
        if (!empty($user)) {
            $username = $user;
            $userNum = (int)$user;
            $c = array();
            if (!empty($userNum)) {
                $c['id'] = $userNum;
            } else {
                $c['username'] = $username;
            }
            $this->user = $this->modx->getObject('modUser',$c);
            if (!$this->user) {
                $this->modx->log(modX::LOG_LEVEL_ERROR,'Could not find user: '.$username);
                $this->user = false;
            }
        /* just use current user if user is logged in */
        } else {
            if (!$this->modx->user->hasSessionContext($this->modx->context->get('key'))) {
                $this->user = false;
            } else {
                $this->user =& $this->modx->user;
            }
        }
        return $this->user;
    }
}
return 'LoginProfileController';