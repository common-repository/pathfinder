<?php

class Pathfinder_Script_Tags
{
    const PATHFINDER_INLINE = "pf-inline";
    const PATHFINDER_SCRIPT = "pf-script";
    const PATHFINDER_WOO = "pf-woo";

    static function getConstants()
    {
        $oClass = new ReflectionClass(__CLASS__);

        return $oClass->getConstants();
    }
}
