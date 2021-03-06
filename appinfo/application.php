<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 */

namespace OCA\News\AppInfo;

require_once __DIR__ . '/autoload.php';

use \PicoFeed\Config\Config as PicoFeedConfig;
use \PicoFeed\Reader\Reader as PicoFeedReader;

use \OC\Files\View;
use \OCP\AppFramework\App;
use \OCP\Util;
use \OCP\User;

use \OCA\News\Config\AppConfig;
use \OCA\News\Config\Config;

use \OCA\News\Controller\AdminController;
use \OCA\News\Controller\PageController;
use \OCA\News\Controller\FolderController;
use \OCA\News\Controller\FeedController;
use \OCA\News\Controller\ItemController;
use \OCA\News\Controller\ExportController;
use \OCA\News\Controller\UtilityApiController;
use \OCA\News\Controller\FolderApiController;
use \OCA\News\Controller\FeedApiController;
use \OCA\News\Controller\ItemApiController;

use \OCA\News\Service\FolderService;
use \OCA\News\Service\FeedService;
use \OCA\News\Service\ItemService;

use \OCA\News\Db\FolderMapper;
use \OCA\News\Db\FeedMapper;
use \OCA\News\Db\ItemMapper;
use \OCA\News\Db\MapperFactory;
use \OCA\News\Db\StatusFlag;

use \OCA\News\Utility\OPMLExporter;
use \OCA\News\Utility\Updater;
use \OCA\News\Utility\PicoFeedClientFactory;
use \OCA\News\Utility\PicoFeedFaviconFactory;
use \OCA\News\Utility\ProxyConfigParser;

use \OCA\News\Fetcher\Fetcher;
use \OCA\News\Fetcher\FeedFetcher;
use \OCA\News\Fetcher\YoutubeFetcher;

use \OCA\News\ArticleEnhancer\Enhancer;
use \OCA\News\ArticleEnhancer\GlobalArticleEnhancer;
use \OCA\News\ArticleEnhancer\XPathArticleEnhancer;
use \OCA\News\ArticleEnhancer\RegexArticleEnhancer;

use \OCA\News\Explore\RecommendedSites;


class Application extends App {

    public function __construct(array $urlParams=[]) {
        parent::__construct('news', $urlParams);

        $container = $this->getContainer();


        /**
         * Controllers
         */
        $container->registerService('PageController', function($c) {
            return new PageController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('CoreConfig'),
                $c->query('URLGenerator'),
                $c->query('AppConfig'),
                $c->query('Config'),
                $c->query('L10N'),
                $c->query('RecommendedSites'),
                $c->query('UserId')
            );
        });

        $container->registerService('AdminController', function($c) {
            return new AdminController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('Config'),
                $c->query('ConfigPath')
            );
        });

        $container->registerService('FolderController', function($c) {
            return new FolderController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FolderService'),
                $c->query('FeedService'),
                $c->query('ItemService'),
                $c->query('UserId')
            );
        });

        $container->registerService('FeedController', function($c) {
            return new FeedController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FolderService'),
                $c->query('FeedService'),
                $c->query('ItemService'),
                $c->query('CoreConfig'),
                $c->query('UserId')
            );
        });

        $container->registerService('ItemController', function($c) {
            return new ItemController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FeedService'),
                $c->query('ItemService'),
                $c->query('CoreConfig'),
                $c->query('UserId')
            );
        });

        $container->registerService('ExportController', function($c) {
            return new ExportController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FolderService'),
                $c->query('FeedService'),
                $c->query('ItemService'),
                $c->query('OPMLExporter'),
                $c->query('UserId')
            );
        });

        $container->registerService('UtilityApiController', function($c) {
            return new UtilityApiController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('Updater'),
                $c->query('CoreConfig')
            );
        });

        $container->registerService('FolderApiController', function($c) {
            return new FolderApiController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FolderService'),
                $c->query('ItemService'),
                $c->query('UserId')
            );
        });

        $container->registerService('FeedApiController', function($c) {
            return new FeedApiController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('FeedService'),
                $c->query('ItemService'),
                $c->query('Logger'),
                $c->query('UserId'),
                $c->query('LoggerParameters')
            );
        });

        $container->registerService('ItemApiController', function($c) {
            return new ItemApiController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('ItemService'),
                $c->query('UserId')
            );
        });

        /**
         * Business Layer
         */
        $container->registerService('FolderService', function($c) {
            return new FolderService(
                $c->query('FolderMapper'),
                $c->query('L10N'),
                $c->query('TimeFactory'),
                $c->query('Config')
            );
        });

        $container->registerService('FeedService', function($c) {
            return new FeedService(
                $c->query('FeedMapper'),
                $c->query('Fetcher'),
                $c->query('ItemMapper'),
                $c->query('Logger'),
                $c->query('L10N'),
                $c->query('TimeFactory'),
                $c->query('Config'),
                $c->query('Enhancer'),
                $c->query('HTMLPurifier'),
                $c->query('LoggerParameters')
            );
        });

        $container->registerService('ItemService', function($c) {
            return new ItemService(
                $c->query('ItemMapper'),
                $c->query('StatusFlag'),
                $c->query('TimeFactory'),
                $c->query('Config')
            );
        });

        // compability for plugins pre 3.0
        $container->registerService('FolderBusinessLayer', function($c) {
            return $c->query('FolderService');
        });
        $container->registerService('FeedBusinessLayer', function($c) {
            return $c->query('FeedService');
        });
        $container->registerService('ItemBusinessLayer', function($c) {
            return $c->query('ItemService');
        });

        /**
         * Mappers
         */
        $container->registerService('MapperFactory', function($c) {
            return new MapperFactory(
                $c->query('DatabaseType'),
                $c->query('Db')
            );
        });

        $container->registerService('FolderMapper', function($c) {
            return new FolderMapper(
                $c->query('Db')
            );
        });

        $container->registerService('FeedMapper', function($c) {
            return new FeedMapper(
                $c->query('Db')
            );
        });

        $container->registerService('ItemMapper', function($c) {
            return $c->query('MapperFactory')->getItemMapper(
                $c->query('Db')
            );
        });


        /**
         * App config parser
         */
        $container->registerService('AppConfig', function($c) {
            $config = new AppConfig(
                $c->query('ServerContainer')->getNavigationManager(),
                $c->query('URLGenerator')
            );

            $config->loadConfig(__DIR__ . '/info.xml');

            return $config;
        });

        /**
         * Core
         */
        $container->registerService('L10N', function($c) {
            return $c->query('ServerContainer')->getL10N($c->query('AppName'));
        });

        $container->registerService('URLGenerator', function($c) {
            return $c->query('ServerContainer')->getURLGenerator();
        });

        $container->registerService('UserId', function() {
            return User::getUser();
        });

        $container->registerService('Logger', function($c) {
            return $c->query('ServerContainer')->getLogger();
        });

        $container->registerService('LoggerParameters', function($c) {
            return ['app' => $c->query('AppName')];
        });

        $container->registerService('Db', function($c) {
            return $c->query('ServerContainer')->getDb();
        });

        $container->registerService('CoreConfig', function($c) {
            return $c->query('ServerContainer')->getConfig();
        });

        $container->registerService('DatabaseType', function($c) {
            return $c->query('ServerContainer')
                ->getConfig()->getSystemValue('dbtype');
        });


        /**
         * Utility
         */
        $container->registerService('ConfigView', function() {
            $view = new View('/news/config');
            if (!$view->file_exists('')) {
                $view->mkdir('');
            }

            return $view;
        });

        $container->registerService('ConfigPath', function() {
            return 'config.ini';
        });

        $container->registerService('Config', function($c) {
            $config = new Config(
                $c->query('ConfigView'),
                $c->query('Logger'),
                $c->query('LoggerParameters')
            );
            $config->read($c->query('ConfigPath'), true);
            return $config;
        });

        $container->registerService('HTMLPurifier', function($c) {
            $directory = $c->query('CoreConfig')
                ->getSystemValue('datadirectory') . '/news/cache/purifier';

            if(!is_dir($directory)) {
                mkdir($directory, 0770, true);
            }

            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.ForbiddenAttributes', 'class');
            $config->set('Cache.SerializerPath', $directory);
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp',
                '%^(?:https?:)?//(' .
                'www.youtube(?:-nocookie)?.com/embed/|' .
                'player.vimeo.com/video/)%'); //allow YouTube and Vimeo
            return new \HTMLPurifier($config);
        });

        $container->registerService('GlobalArticleEnhancer', function() {
            return new GlobalArticleEnhancer();
        });

        $container->registerService('Enhancer', function($c) {
            $enhancer = new Enhancer();

            // register simple enhancers from config json file
            $xpathEnhancerConfig = file_get_contents(
                __DIR__ . '/../articleenhancer/xpathenhancers.json'
            );

            $xpathEnhancerConfig = json_decode($xpathEnhancerConfig, true);
            foreach($xpathEnhancerConfig as $feed => $config) {
                $articleEnhancer = new XPathArticleEnhancer(
                    $c->query('PicoFeedClientFactory'),
                    $config
                );
                $enhancer->registerEnhancer($feed, $articleEnhancer);
            }

            $regexEnhancerConfig = file_get_contents(
                __DIR__ . '/../articleenhancer/regexenhancers.json'
            );
            $regexEnhancerConfig = json_decode($regexEnhancerConfig, true);
            foreach($regexEnhancerConfig as $feed => $config) {
                foreach ($config as $matchArticleUrl => $regex) {
                    $articleEnhancer =
                        new RegexArticleEnhancer($matchArticleUrl, $regex);
                    $enhancer->registerEnhancer($feed, $articleEnhancer);
                }
            }

            $enhancer->registerGlobalEnhancer(
                $c->query('GlobalArticleEnhancer')
            );

            return $enhancer;
        });

        /**
         * Fetchers
         */
        $container->registerService('PicoFeedConfig', function($c) {
            // FIXME: move this into a separate class for testing?
            $config = $c->query('Config');
            $appConfig = $c->query('AppConfig');
            $proxy =  $c->query('ProxyConfigParser');

            $userAgent = 'ownCloud News/' . $appConfig->getConfig('version') .
                         ' (+https://owncloud.org/; 1 subscriber;)';

            $pico = new PicoFeedConfig();
            $pico->setClientUserAgent($userAgent)
                ->setClientTimeout($config->getFeedFetcherTimeout())
                ->setMaxRedirections($config->getMaxRedirects())
                ->setMaxBodySize($config->getMaxSize())
                // enable again if we can distinguish between security and
                // content filtering
                //->setContentFiltering(false)
                ->setParserHashAlgo('md5');

            // proxy settings
            $proxySettings = $proxy->parse();
            $host = $proxySettings['host'];
            $port = $proxySettings['port'];
            $user = $proxySettings['user'];
            $password = $proxySettings['password'];

            if ($host) {
                $pico->setProxyHostname($host);

                if ($port) {
                    $pico->setProxyPort($port);
                }
            }

            if ($user) {
                $pico->setProxyUsername($user)
                    ->setProxyPassword($password);
            }

            return $pico;
        });

        $container->registerService('PicoFeedReader', function($c) {
            return new PicoFeedReader($c->query('PicoFeedConfig'));
        });

        $container->registerService('PicoFeedClientFactory', function($c) {
            return new PicoFeedClientFactory($c->query('PicoFeedConfig'));
        });

        $container->registerService('PicoFeedFaviconFactory', function($c) {
            return new PicoFeedFaviconFactory($c->query('PicoFeedConfig'));
        });

        $container->registerService('Fetcher', function($c) {
            $fetcher = new Fetcher();

            // register fetchers in order
            // the most generic fetcher should be the last one
            $fetcher->registerFetcher($c->query('YoutubeFetcher'));
            $fetcher->registerFetcher($c->query('FeedFetcher'));

            return $fetcher;
        });

        $container->registerService('FeedFetcher', function($c) {
            return new FeedFetcher(
                $c->query('PicoFeedReader'),
                $c->query('PicoFeedFaviconFactory'),
                $c->query('L10N'),
                $c->query('TimeFactory')
            );
        });

        $container->registerService('YoutubeFetcher', function($c) {
            return new YoutubeFetcher(
                $c->query('FeedFetcher')
            );
        });

        $container->registerService('StatusFlag', function() {
            return new StatusFlag();
        });

        $container->registerService('OPMLExporter', function() {
            return new OPMLExporter();
        });

        $container->registerService('ProxyConfigParser', function($c) {
            return new ProxyConfigParser(
                $c->query('CoreConfig')
            );
        });

        $container->registerService('Updater', function($c) {
            return new Updater(
                $c->query('FolderService'),
                $c->query('FeedService'),
                $c->query('ItemService')
            );
        });

        $container->registerService('RecommendedSites', function($c) {
            return new RecommendedSites(__DIR__ . '/../explore');
        });
    }

    public function getAppConfig() {
        return $this->getContainer()->query('AppConfig');
    }


    public function getLogger() {
        return $this->getContainer()->query('Logger');
    }


    public function getLoggerParameters() {
        return $this->getContainer()->query('LoggerParameters');
    }


}

