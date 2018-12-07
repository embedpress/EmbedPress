<?php

namespace EmbedPress;

use EmbedPress\Ends\Back\Handler as EndHandlerAdmin;
use EmbedPress\Ends\Front\Handler as EndHandlerPublic;

(defined('ABSPATH') && defined('EMBEDPRESS_IS_LOADED')) or die("No direct script access allowed.");

/**
 * Entity that glues together all pieces that the plugin is made of, for WordPress 5+.
 *
 * @package     EmbedPress
 * @author      EmbedPress <help@embedpress.com>
 * @copyright   Copyright (C) 2018 EmbedPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */
class Core
{
    /**
     * The name of the plugin.
     *
     * @since   1.0.0
     * @access  protected
     *
     * @var     string $pluginName The name of the plugin.
     */
    protected $pluginName;

    /**
     * The version of the plugin.
     *
     * @since   1.0.0
     * @access  protected
     *
     * @var     string $pluginVersion The version of the plugin.
     */
    protected $pluginVersion;

    /**
     * An instance of the plugin loader.
     *
     * @since   1.0.0
     * @access  protected
     *
     * @var     \EmbedPress\Loader $pluginVersion The version of the plugin.
     */
    protected $loaderInstance;

    /**
     * An associative array storing all registered/active EmbedPress plugins and their namespaces.
     *
     * @since   1.4.0
     * @access  private
     * @static
     *
     * @var     array
     */
    private static $plugins = [];

    /**
     * Initialize the plugin and set its properties.
     *
     * @since   1.0.0
     *
     * @return  void
     */
    public function __construct()
    {
        $this->pluginName    = EMBEDPRESS_PLG_NAME;
        $this->pluginVersion = EMBEDPRESS_PLG_VERSION;

        $this->loaderInstance = new Loader();
    }

    /**
     * Method that retrieves the plugin name.
     *
     * @since   1.0.0
     *
     * @return  string
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * Method that retrieves the plugin version.
     *
     * @since   1.0.0
     *
     * @return  string
     */
    public function getPluginVersion()
    {
        return $this->pluginVersion;
    }

    /**
     * Method that retrieves the loader instance.
     *
     * @since   1.0.0
     *
     * @return  \EmbedPress\Loader
     */
    public function getLoader()
    {
        return $this->loaderInstance;
    }

    /**
     * Method responsible to connect all required hooks in order to make the plugin work.
     *
     * @since   1.0.0
     *
     * @return  void
     */
    public function initialize()
    {
        global $wp_actions;

        add_filter('oembed_providers', [$this, 'addOEmbedProviders']);
        add_action('rest_api_init', [$this, 'registerOEmbedRestRoutes']);
//        add_filter('oembed_result', [$this, 'oembedResult'], 10, 3);

        if (is_admin()) {
            $plgSettings = self::getSettings();

            $settingsClassNamespace = '\\EmbedPress\\Ends\\Back\\Settings';
            add_action('admin_menu', [$settingsClassNamespace, 'registerMenuItem']);
            add_action('admin_init', [$settingsClassNamespace, 'registerActions']);
            unset($settingsClassNamespace);

            add_filter('plugin_action_links_embedpress/embedpress.php', ['\\EmbedPress\\Core', 'handleActionLinks'], 10,
                2);

            add_action('admin_enqueue_scripts', ['\\EmbedPress\\Ends\\Back\\Handler', 'enqueueStyles']);

            $plgHandlerAdminInstance = new EndHandlerAdmin($this->getPluginName(), $this->getPluginVersion());

            if ((bool)$plgSettings->enablePluginInAdmin) {
                $this->loaderInstance->add_action('admin_enqueue_scripts', $plgHandlerAdminInstance, 'enqueueScripts');
            }
        } else {
            $plgHandlerPublicInstance = new EndHandlerPublic($this->getPluginName(), $this->getPluginVersion());

            $this->loaderInstance->add_action('wp_enqueue_scripts', $plgHandlerPublicInstance, 'enqueueScripts');
            $this->loaderInstance->add_action('wp_enqueue_scripts', $plgHandlerPublicInstance, 'enqueueStyles');

            unset($plgHandlerPublicInstance);
        }

        // Add support for embeds on AMP pages
        add_filter('pp_embed_parsed_content', ['\\EmbedPress\\AMP\\EmbedHandler', 'processParsedContent'], 10, 3);

        // Add support for our embeds on Beaver Builder. Without this it only run the native embeds.
        add_filter('fl_builder_before_render_shortcodes',
            ['\\EmbedPress\\ThirdParty\\BeaverBuilder', 'before_render_shortcodes']);

        $this->loaderInstance->run();
    }

    public function oembedResult($html, $url, $args)
    {
        // apply_filters( 'oembed_result', $this->data2html( $data, $url ), $url, $args );
        var_dump($html, $url, $args); die;

        return $html;
    }

    /**
     * @param $providers
     *
     * @return mixed
     */
    public function addOEmbedProviders($providers)
    {
        $newProviders = [
            // Viddler
            '#https?://(.+\.)?viddler\.com/v/.+#i' => 'viddler',

            // Deviantart.com (http://www.deviantart.com)
            '#https?://(.+\.)?deviantart\.com/art/.+#i' => 'devianart',
            '#https?://(.+\.)?deviantart\.com/.*/d.+#i' => 'devianart',
            '#https?://(.+\.)?fav\.me/.+#i' => 'devianart',
            '#https?://(.+\.)?sta\.sh/.+#i' => 'devianart',

            // chirbit.com (http://www.chirbit.com/)
            '#https?://(.+\.)?chirb\.it/.+#i' => 'chirbit',

            // nfb.ca (http://www.nfb.ca/)
            '#https?://(.+\.)?nfb\.ca/film/.+#i' => 'nfb',

            // Dotsub (http://dotsub.com/)
            '#https?://(.+\.)?dotsub\.com/view/.+#i' => 'dotsub',

            // Rdio (http://rdio.com/)
            '#https?://(.+\.)?rdio\.com/(artist|people)/.+#i'  => 'rdio',

            // Sapo Videos (http://videos.sapo.pt)
            '#https?://(.+\.)?videos\.sapo\.pt/.+#i' => 'sapo',

            // Official FM (http://official.fm)
            '#https?://(.+\.)?official\.fm/(tracks|playlists)/.+#i' => 'officialfm',

            // HuffDuffer (http://huffduffer.com)
            '#https?://(.+\.)?huffduffer\.com/.+#i' => 'huffduffer',

            // Shoudio (http://shoudio.com)
            '#https?://(.+\.)?shoudio\.(com|io)/.+#i' => 'shoudio',

            // Moby Picture (http://www.mobypicture.com)
            '#https?://(.+\.)?mobypicture\.com/user/.+/view/.+#i' => 'mobypicture',
            '#https?://(.+\.)?moby\.to/.+#i',

            // 23HQ (http://www.23hq.com)
            '#https?://(.+\.)?23hq\.com/.+/photo/.+#i' => '23hq',

            // Cacoo (https://cacoo.com)
            '#https?://(.+\.)?cacoo\.com/diagrams/.+#i' => 'cacoo',

            // Dipity (http://www.dipity.com)
            '#https?://(.+\.)?dipity\.com/.+#i' => 'dipity',

            // Roomshare (http://roomshare.jp)
            '#https?://(.+\.)?roomshare\.jp/(en/)?post/.+#i' => 'roomshare',

            // Crowd Ranking (http://crowdranking.com)
            '#https?://(.+\.)?c9ng\.com/.+#i' => 'crowd',

            // CircuitLab (https://www.circuitlab.com/)
            '#https?://(.+\.)?circuitlab\.com/circuit/.+#i' => 'circuitlab',

            // Coub (http://coub.com/)
            '#https?://(.+\.)?coub\.com/(view|embed)/.+#i' => 'coub',

            // Ustream (http://www.ustream.tv)
            '#https?://(.+\.)?ustream\.(tv|com)/.+#i' => 'ustream',

            // Daily Mile (http://www.dailymile.com)
            '#https?://(.+\.)?dailymile\.com/people/.+/entries/.+#i' => 'daily',

            // Sketchfab (http://sketchfab.com)
            '#https?://(.+\.)?sketchfab\.com/models/.+#i' => 'sketchfab',
            '#https?://(.+\.)?sketchfab\.com/.+/folders/.+#i' => 'sketchfab',

            // AudioSnaps (http://audiosnaps.com)
            '#https?://(.+\.)?audiosnaps\.com/k/.+#i' => 'audiosnaps',

            // RapidEngage (https://rapidengage.com)
            '#https?://(.+\.)?rapidengage\.com/s/.+#i' => 'rapidengage',

            // Getty Images (http://www.gettyimages.com/)
            '#https?://(.+\.)?gty\.im/.+#i' => 'gettyimages',
            '#https?://(.+\.)?gettyimages\.com/detail/photo/.+#i' => 'gettyimages',

            // amCharts Live Editor (http://live.amcharts.com/)
            '#https?://(.+\.)?live\.amcharts\.com/.+#i' => 'amcharts',

            // Infogram (https://infogr.am/)
            '#https?://(.+\.)?infogr\.am/.+#i' => 'infogram',

            // ChartBlocks (http://www.chartblocks.com/)
            '#https?://(.+\.)?public\.chartblocks\.com/c/.+#i' => 'chartblocks',

            // ReleaseWire (http://www.releasewire.com/)
            '#https?://(.+\.)?rwire\.com/.+#i' => 'releasewire',

            // ShortNote (https://www.shortnote.jp/)
            '#https?://(.+\.)?shortnote\.jp/view/notes/.+#i' => 'shortnote',

            // EgliseInfo (http://egliseinfo.catholique.fr/)
            '#https?://(.+\.)?egliseinfo\.catholique\.fr/.+#i' => 'egliseinfo',

            // Silk (http://www.silk.co/)
            '#https?://(.+\.)?silk\.co/explore/.+#i' => 'silk',
            '#https?://(.+\.)?silk\.co/s/embed/.+#i' => 'silk',

            // http://bambuser.com
            '#https?://(.+\.)?bambuser\.com/v/.+#i' => 'bambuser',

            // https://clyp.it
            '#https?://(.+\.)?clyp\.it/.+#i' => 'clyp',

            // https://gist.github.com
            '#https?://(.+\.)?gist\.github\.com/.+#i' => 'github',

            // https://portfolium.com
            '#https?://(.+\.)?portfolium\.com/.+#i' => 'portfolium',

            // http://rutube.ru
            '#https?://(.+\.)?rutube\.ru/video/.+#i' => 'rutube',

            // http://www.videojug.com
            '#https?://(.+\.)?videojug\.com/.+#i' => 'videojug',

            // https://vine.com
            '#https?://(.+\.)?vine\.co/v/.+#i' => 'vine',

            // Google Shortened Url
            '#https?://(.+\.)?goo\.gl/.+#i' => 'google',

            // Google Maps
            '#https?://(.+\.)?google\.com/maps/.+#i' => 'googlemaps',
            '#https?://(.+\.)?google\.com/.+#i' => 'googlemaps',
            '#https?://(.+\.)?google\.com\.*/.+#i' => 'googlemaps',
            '#https?://(.+\.)?google\.co\.*/.+#i' => 'googlemaps',
            '#https?://(.+\.)?maps\.google\.com/.+#i' => 'googlemaps',

            // Google Docs
            '#https?://(.+\.)?doc\.google\.com/presentation/.+#i' => 'googledocs',
            '#https?://(.+\.)?doc\.google\.com/document/.+#i' => 'googledocs',
            '#https?://(.+\.)?doc\.google\.com/spreadsheets/.+#i' => 'googledocs',
            '#https?://(.+\.)?doc\.google\.com/forms/.+#i' => 'googledocs',
            '#https?://(.+\.)?doc\.google\.com/drawings/.+#i' => 'googledocs',

            // Twitch.tv
            '#https?://(.+\.)?twitch\.tv/.+#i' => 'twitch',

            // Giphy
            '#https?://(.+\.)?giphy\.com/gifs/.+#i' => 'giphy',
            '#https?://(.+\.)?i\.giphy\.com/.+#i' => 'giphy',
            '#https?://(.+\.)?gph\.is/.+#i' => 'giphy',

            // Wistia
            '#https?://(.+\.)?wistia\.com/medias/.+#i' => 'wistia',
            '#https?://(.+\.)?fast\.wistia\.com/embed/medias/.+#i\.jsonp' => 'wistia',
        ];

        foreach ($newProviders as $url => &$data) {
            $data = [rest_url('embedpress/v1/oembed/' . $data), true];
        }

        $providers = array_merge($providers, $newProviders);

        return $providers;
    }

    /**
     * Register OEmbed Rest Routes
     */
    public function registerOEmbedRestRoutes()
    {
        register_rest_route(
            'embedpress/v1', '/oembed/(?P<provider>[a-zA-Z0-9\-]+)',
            [
                'methods' => 'GET',
                'callback' => ['\\EmbedPress\\RestAPI', 'oembed'],
            ]
        );
    }

    /**
     * Callback called right after the plugin has been activated.
     *
     * @since   1.0.0
     * @static
     *
     * @return  void
     */
    public static function onPluginActivationCallback()
    {
        flush_rewrite_rules();
    }

    /**
     * Callback called right after the plugin has been deactivated.
     *
     * @since   1.0.0
     * @static
     *
     * @return  void
     */
    public static function onPluginDeactivationCallback()
    {
        flush_rewrite_rules();
    }

    /**
     * Method that retrieves all additional service providers defined in the ~<plugin_root_path>/providers.php file.
     *
     * @since   1.0.0
     * @static
     *
     * @return  array
     */
    public static function getAdditionalServiceProviders()
    {
        $additionalProvidersFilePath = EMBEDPRESS_PATH_BASE . 'providers.php';
        if (file_exists($additionalProvidersFilePath)) {
            include $additionalProvidersFilePath;

            if (isset($additionalServiceProviders)) {
                return $additionalServiceProviders;
            }
        }

        return [];
    }

    /**
     * Method that checks if an embed of a given service provider can be responsive.
     *
     * @since   1.0.0
     * @static
     *
     * @param   string $serviceProviderAlias The service's slug.
     *
     * @return  boolean
     */
    public static function canServiceProviderBeResponsive($serviceProviderAlias)
    {
        return in_array($serviceProviderAlias, [
            "dailymotion",
            "kickstarter",
            "rutube",
            "ted",
            "vimeo",
            "youtube",
            "ustream",
            "google-docs",
            "animatron",
            "amcharts",
            "on-aol-com",
            "animoto",
            "videojug",
            'issuu',
        ]);
    }

    /**
     * Method that retrieves the plugin settings defined by the user.
     *
     * @since   1.0.0
     * @static
     *
     * @return  object
     */
    public static function getSettings()
    {
        $settings = get_option(EMBEDPRESS_PLG_NAME);

        if ( ! isset($settings['enablePluginInAdmin'])) {
            $settings['enablePluginInAdmin'] = true;
        }

        if ( ! isset($settings['enablePluginInFront'])) {
            $settings['enablePluginInFront'] = true;
        }

        return (object)$settings;
    }

    /**
     * Method that register an EmbedPress plugin.
     *
     * @since   1.4.0
     * @static
     *
     * @param   array $pluginMeta Associative array containing plugin's name, slug and namespace
     *
     * @return  void
     */
    public static function registerPlugin($pluginMeta)
    {
        $pluginMeta = json_decode(json_encode($pluginMeta));

        if (empty($pluginMeta->name) || empty($pluginMeta->slug) || empty($pluginMeta->namespace)) {
            return;
        }

        if ( ! isset(self::$plugins[$pluginMeta->slug])) {
            AutoLoader::register($pluginMeta->namespace,
                WP_PLUGIN_DIR . '/' . EMBEDPRESS_PLG_NAME . '-' . $pluginMeta->slug . '/' . $pluginMeta->name);

            $plugin = "{$pluginMeta->namespace}\Plugin";
            if (\defined("{$plugin}::SLUG") && $plugin::SLUG !== null) {
                self::$plugins[$pluginMeta->slug] = $pluginMeta->namespace;

                $bsFilePath = $plugin::PATH . EMBEDPRESS_PLG_NAME . '-' . $plugin::SLUG . '.php';

                register_activation_hook($bsFilePath, [$plugin::NAMESPACE_STRING, 'onActivationCallback']);
                register_deactivation_hook($bsFilePath, [$plugin::NAMESPACE_STRING, 'onDeactivationCallback']);

                add_action('admin_init', [$plugin, 'onLoadAdminCallback']);

                add_action(EMBEDPRESS_PLG_NAME . ':' . $plugin::SLUG . ':settings:register',
                    [$plugin, 'registerSettings']);
                add_action(EMBEDPRESS_PLG_NAME . ':settings:render:tab', [$plugin, 'renderTab']);

                add_filter('plugin_action_links_embedpress-' . $plugin::SLUG . '/embedpress-' . $plugin::SLUG . '.php',
                    [$plugin, 'handleActionLinks'], 10, 2);

                $plugin::registerEvents();
            }
        }
    }

    /**
     * Retrieve all registered plugins.
     *
     * @since   1.4.0
     * @static
     *
     * @return  array
     */
    public static function getPlugins()
    {
        return self::$plugins;
    }

    /**
     * Handle links displayed below the plugin name in the WordPress Installed Plugins page.
     *
     * @since   1.4.0
     * @static
     *
     * @return  array
     */
    public static function handleActionLinks($links, $file)
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=embedpress') . '" aria-label="' . __('Open settings page',
                'embedpress') . '">' . __('Settings', 'embedpress') . '</a>';

        array_unshift($links, $settingsLink);

        return $links;
    }

    /**
     * Method that ensures the API's url are whitelisted to WordPress external requests.
     *
     * @since   1.4.0
     * @static
     *
     * @param   boolean $isAllowed
     * @param   string  $host
     * @param   string  $url
     *
     * @return  boolean
     */
    public static function allowApiHost($isAllowed, $host, $url)
    {
        if ($host === EMBEDPRESS_LICENSES_API_HOST) {
            $isAllowed = true;
        }

        return $isAllowed;
    }
}