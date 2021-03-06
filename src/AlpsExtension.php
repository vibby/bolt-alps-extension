<?php

namespace Bolt\Extension\Vibby\Alps;

use Bolt\Asset\Widget\Widget;
use Bolt\Extension\Vibby\Alps\Command\ImportCommand;
use Bolt\Menu\MenuEntry;
use Silex\ControllerCollection;
use Silex\Application;
use Bolt\Extension\SimpleExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ExtensionName extension class.
 *
 * @author vibby <vincent@beauvivre.fr>
 */
class AlpsExtension extends SimpleExtension
{
    protected function registerServices(Application $app)
    {
        setlocale (LC_TIME, $this->getConfig()['php_locale'][0], $this->getConfig()['php_locale'][1]);

        $app['twig'] = $app->share($app->extend(
            'twig',
            function (\Twig_Environment $twig) use ($app) {
                /** @var \Twig_Loader_Chain $twigChainLoader */
                $twigChainLoader = $twig->getLoader();

                $alpsPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'
                    .DIRECTORY_SEPARATOR.'adventistchurch'.DIRECTORY_SEPARATOR.'alps'.DIRECTORY_SEPARATOR;

                $twigLoader = new \Twig_Loader_Filesystem();
                // level 1 : take template customized from this extension
                $twigLoader->addPath(dirname(__DIR__).'/templates/_patterns/00-atoms', 'atoms');
                $twigLoader->addPath(dirname(__DIR__).'/templates/_patterns/01-molecules', 'molecules');
                $twigLoader->addPath(dirname(__DIR__).'/templates/_patterns/02-organisms', 'organisms');
                $twigLoader->addPath(dirname(__DIR__).'/templates/_patterns/03-templates', 'templates');
                // level 2 : take ALPS template, if previous not found
                $twigLoader->addPath($alpsPath.'source/_patterns/00-atoms', 'atoms');
                $twigLoader->addPath($alpsPath.'source/_patterns/01-molecules', 'molecules');
                $twigLoader->addPath($alpsPath.'source/_patterns/02-organisms', 'organisms');
                $twigLoader->addPath($alpsPath.'source/_patterns/03-templates', 'templates');
                // in addition : create suffixed for all ALPS template, to use them in customized templates
                $twigLoader->addPath($alpsPath.'source/_patterns/00-atoms', 'atoms_source');
                $twigLoader->addPath($alpsPath.'source/_patterns/01-molecules', 'molecules_source');
                $twigLoader->addPath($alpsPath.'source/_patterns/02-organisms', 'organisms_source');
                $twigLoader->addPath($alpsPath.'source/_patterns/03-templates', 'templates_source');
                $twigChainLoader->addLoader($twigLoader);
                $twig->setLoader($twigChainLoader);

                // Define all defaults data from ALPS json
                $alpsConfig = json_decode(file_get_contents($alpsPath.'source/_data/data.json'), true);
                $alpsConfig['logo_bottom_text']['text'] =  $this->getConfig()['logo_text'];
                foreach ($alpsConfig as $key => $data) {
                    $twig->addGlobal($key, $data);
                }

                $twig->addGlobal('alps', $this->getConfig());
                $twig->addGlobal('image_path', $this->getConfig()['path_cdn'].'images/');

                return $twig;
            }
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function registerNutCommands(\Pimple $container)
    {
        return [
            new ImportCommand(),
        ];
    }

    protected function registerAssets()
    {
        $widgetObj = new Widget();
        $widgetObj
            ->setZone('frontend')
            ->setLocation('aside_top')
            ->setCallback([$this, 'getPlanningWidget'])
            ->setCallbackArguments([])
            ->setDefer(false)
        ;

        return [ $widgetObj ];
    }

    public function getPlanningWidget()
    {
        return $this->renderTemplate('widgets/planning.twig');
    }

    public function registerTwigFilters()
    {
        return [
            'humanizeDate' => 'humanizeDate',
        ];
    }

    public function humanizeDate($dateTime)
    {
        $dateTime = ($dateTime instanceof \DateTime) ? $dateTime->format('U') : strtotime($dateTime);

        return ucwords(strftime($this->getConfig()['date_format'], $dateTime));
    }

    protected function registerMenuEntries()
    {
        $menu = new MenuEntry('dates-menu', '/bolt/dates');
        $menu->setLabel('Dates Importer')
            ->setIcon('fa:calendar')
            ->setPermission('settings')
        ;

        return [
            $menu,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        return [
            '/dates' => new Controller\DatesController(),
        ];
    }
}
