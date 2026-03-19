<?php

namespace Weathermap\Map;

use Weathermap\Base\MapBase;
use Weathermap\Base\DataSource;
use Weathermap\Base\PreProcessor;
use Weathermap\Base\PostProcessor;
use Weathermap\Base\InternalException;
use Weathermap\Html\ImageMap;
use Weathermap\Util\Font;
use Weathermap\Util\Colour;
use Weathermap\Geometry\Point;
use Weathermap\Geometry\Vector;
use Weathermap\Geometry\Line;
use Weathermap\Geometry\LineSegment;
use Weathermap\Map\Node;
use Weathermap\Map\Link;

class WeatherMap extends MapBase
{
    const VERSION = '0.98';

    use ConfigReader;
    use DataProcessor;
    use ImageRenderer;
    use HtmlGenerator;
    use ConfigWriter;
    use CacheManager;
    use PluginManager;

    public $nodes = array(); // an array of WeatherMapNodes
    public $links = array(); // an array of WeatherMapLinks
    public $texts = array(); // an array containing all the extraneous text bits
    public $used_images = array(); // an array of image filenames referred to (used by editor)
    public $seen_zlayers = array(0 => array(), 1000 => array()); // 0 is the background, 1000 is the legends, title, etc

	public $config;
	public $next_id;
	public $min_ds_time;
	public $max_ds_time;
	public $background;
	public $htmlstyle;
	public $imap;
	public $colours;
	public $configfile;
	public $imagefile,
		$imageuri;
	public $rrdtool;
	public $title,
		$titlefont;
	public $kilo;
	public $sizedebug,
		$widthmod,
		$debugging;
	public $linkfont,
		$nodefont,
		$keyfont,
		$timefont;
	public $timex,
		$timey;
	public $width,
		$height;
	public $keyx,
		$keyy, $keyimage;
	public $titlex,
		$titley;
	public $keytext,
		$stamptext, $datestamp;
	public $min_data_time, $max_data_time;
	public $htmloutputfile,
		$imageoutputfile;
	public $htmlstylesheet;
	public $defaultlink,
		$defaultnode;
	public $need_size_precalc;
	public $keystyle,$keysize;
	public $rrdtool_check;
	public $inherit_fieldlist;
	public $mintimex, $maxtimex;
	public $mintimey, $maxtimey;
	public $minstamptext, $maxstamptext;
	public $context;
	public $cachefolder,$mapcache,$cachefile_version;
	public $name;
	public $black,
		$white,
		$grey,
		$selected;

    public $datasourceclasses;
    public $preprocessclasses;
    public $postprocessclasses;
    public $activedatasourceclasses;
    public $thumb_width, $thumb_height;
    public $has_includes;
    public $has_overlibs;
    public $node_template_tree;
    public $link_template_tree;
    public $dsinfocache = array();
    public $allItemsCache = null;

    public $plugins = array();
    public $included_files = array();
    public $usage_stats = array();
    public $coverage = array();
    public $colourtable = array();
    public $warncount = 0;
    public $numscales = array();
    public $dumpconfig;
    public $labelstyle;
    public $fonts;
    public $basehref;
    public $image;



	function __construct()
	{
		$this->inherit_fieldlist=array
        (
			'width' => 800,
			'height' => 600,
			'kilo' => 1000,
			'numscales' => array('DEFAULT' => 0),
			'datasourceclasses' => array(),
			'preprocessclasses' => array(),
			'postprocessclasses' => array(),
			'included_files' => array(),
			'context' => '',
			'dumpconfig' => FALSE,
			'rrdtool_check' => '',
			'background' => '',
			'imageoutputfile' => '',
			'imageuri' => '',
			'htmloutputfile' => '',
			'htmlstylesheet' => '',
			'labelstyle' => 'percent', // redundant?
			'htmlstyle' => 'static',
			'keystyle' => array('DEFAULT' => 'classic'),
			'title' => 'Network Weathermap',
			'keytext' => array('DEFAULT' => 'Traffic Load'),
			'keyx' => array('DEFAULT' => -1),
			'keyy' => array('DEFAULT' => -1),
			'keyimage' => array(),
			'keysize' => array('DEFAULT' => 400),
			'stamptext' => 'Created: %b %d %Y %H:%M:%S',
			'keyfont' => 4,
			'titlefont' => 2,
			'timefont' => 2,
			'timex' => 0,
			'timey' => 0,

            'mintimex' => -10000,
            'mintimey' => -10000,
            'maxtimex' => -10000,
            'maxtimey' => -10000,
            'minstamptext' => 'Oldest Data: %b %d %Y %H:%M:%S',
            'maxstamptext' => 'Newest Data: %b %d %Y %H:%M:%S',

            'thumb_width' => 0,
            'thumb_height' => 0,
            'titlex' => -1,
            'titley' => -1,
            'cachefolder' => 'cached',
            'mapcache' => '',
            'sizedebug' => false,
            'debugging' => false,
            'widthmod' => false,
            'has_includes' => false,
            'has_overlibs' => false,
            'name' => 'MAP'
        );

        $this->Reset();
    }

    function my_type()
    {
        return "MAP";
    }

    function Reset()
    {
        $this->next_id = 100;
        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            $this->$fld = $this->inherit_fieldlist[$fld];
        }

        $this->min_ds_time = null;
        $this->max_ds_time = null;

        $this->need_size_precalc = false;

        $this->nodes = array(); // an array of WeatherMapNodes
        $this->links = array(); // an array of WeatherMapLinks

        // these are the default defaults
        // by putting them into a normal object, we can use the
        // same code for writing out LINK DEFAULT as any other link.
        wm_debug("Creating ':: DEFAULT ::' DEFAULT LINK\n");
        // these two are used for default settings
        $deflink = new Link;
        $deflink->name = ":: DEFAULT ::";
        $deflink->template = ":: DEFAULT ::";
        $deflink->Reset($this);

        $this->links[':: DEFAULT ::'] = &$deflink;

        wm_debug("Creating ':: DEFAULT ::' DEFAULT NODE\n");
        $defnode = new Node;
        $defnode->name = ":: DEFAULT ::";
        $defnode->template = ":: DEFAULT ::";
        $defnode->Reset($this);

        $this->nodes[':: DEFAULT ::'] = &$defnode;

        $this->node_template_tree = array();
        $this->link_template_tree = array();

        $this->node_template_tree['DEFAULT'] = array();
        $this->link_template_tree['DEFAULT'] = array();


        // ************************************
        // now create the DEFAULT link and node, based on those.
        // these can be modified by the user, but their template (and therefore comparison in WriteConfig) is ':: DEFAULT ::'
        wm_debug("Creating actual DEFAULT NODE from :: DEFAULT ::\n");
        $defnode2 = new Node;
        $defnode2->name = "DEFAULT";
        $defnode2->template = ":: DEFAULT ::";
        $defnode2->Reset($this);

        $this->nodes['DEFAULT'] = &$defnode2;

        wm_debug("Creating actual DEFAULT LINK from :: DEFAULT ::\n");
        $deflink2 = new Link;
        $deflink2->name = "DEFAULT";
        $deflink2->template = ":: DEFAULT ::";
        $deflink2->Reset($this);

        $this->links['DEFAULT'] = &$deflink2;

        $this->defaultnode = &$this->nodes['DEFAULT'];
        $this->defaultlink = &$this->links['DEFAULT'];

        $this->imap = new ImageMap('weathermap');
        $this->colours = array
        ();

        wm_debug("Adding default map colour set.\n");
        $defaults = array
        (
            'KEYTEXT' => array('bottom' => -2, 'top' => -1, 'red1' => 0, 'green1' => 0, 'blue1' => 0, 'special' => 1),
            'KEYOUTLINE' => array(
                'bottom' => -2,
                'top' => -1,
                'red1' => 0,
                'green1' => 0,
                'blue1' => 0,
                'special' => 1
            ),
            'KEYBG' => array(
                'bottom' => -2,
                'top' => -1,
                'red1' => 255,
                'green1' => 255,
                'blue1' => 255,
                'special' => 1
            ),
            'BG' => array('bottom' => -2, 'top' => -1, 'red1' => 255, 'green1' => 255, 'blue1' => 255, 'special' => 1),
            'TITLE' => array('bottom' => -2, 'top' => -1, 'red1' => 0, 'green1' => 0, 'blue1' => 0, 'special' => 1),
            'TIME' => array('bottom' => -2, 'top' => -1, 'red1' => 0, 'green1' => 0, 'blue1' => 0, 'special' => 1)
        );

        foreach ($defaults as $key => $def) {
            $this->colours['DEFAULT'][$key] = $def;
        }

        $this->configfile = '';
        $this->imagefile = '';
        $this->imageuri = '';

        $this->fonts = array();

        // Adding these makes the editor's job a little easier, mainly
        for ($i = 1; $i <= 5; $i++) {
            $this->fonts[$i] = new Font();
            $this->fonts[$i]->type = "GD builtin";
            $this->fonts[$i]->file = '';
            $this->fonts[$i]->size = 0;
        }

        $this->LoadPlugins('data', 'lib' . DIRECTORY_SEPARATOR . 'datasources');
        $this->LoadPlugins('pre', 'lib' . DIRECTORY_SEPARATOR . 'pre');
        $this->LoadPlugins('post', 'lib' . DIRECTORY_SEPARATOR . 'post');

        wm_debug("WeatherMap class Reset() complete\n");
    }
}
