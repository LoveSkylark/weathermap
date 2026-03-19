<?php

namespace Weathermap\Base;

// Links, Nodes and the Map object inherit from this class ultimately.
// Just to make some common code common.
class MapBase
{
    var $notes = array();
    var $hints = array();
    var $inherit_fieldlist;

    function add_note($name, $value)
    {
        wm_debug("Adding note $name='$value' to " . $this->name . "\n");
        $this->notes[$name] = $value;
    }

    function get_note($name)
    {
        if (isset($this->notes[$name])) {
            //	debug("Found note $name in ".$this->name." with value of ".$this->notes[$name].".\n");
            return ($this->notes[$name]);
        } else {
            //	debug("Looked for note $name in ".$this->name." which doesn't exist.\n");
            return (null);
        }
    }

    function add_hint($name, $value)
    {
        wm_debug("Adding hint $name='$value' to " . $this->name . "\n");
        $this->hints[$name] = $value;
        # warn("Adding hint $name to ".$this->my_type()."/".$this->name."\n");
    }


    function get_hint($name, $default = null)
    {
        if (isset($this->hints[$name])) {
            //	debug("Found hint $name in ".$this->name." with value of ".$this->hints[$name].".\n");
            return ($this->hints[$name]);
        }
        //	debug("Looked for hint $name in ".$this->name." which doesn't exist.\n");
        return $default;
    }
}
