<?php

namespace Weathermap\Base;

// The 'things on the map' class. More common code (mainly variables, actually)
class MapItem extends MapBase
{
    var $owner;

    var $configline;
    var $infourl;
    var $overliburl;
    var $overlibwidth, $overlibheight;
    var $overlibcaption;
    var $my_default;
    var $defined_in;
    var $config_override;    # used by the editor to allow text-editing

    function my_type()
    {
        return "ITEM";
    }
}
