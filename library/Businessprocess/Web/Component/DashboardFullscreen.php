<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Exception;
use Icinga\Application\Modules\Module;
use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\Storage;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class DashboardFullscreen extends BaseHtmlElement
{
    /** @var string */
    protected $contentSeparator = "\n";

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'overview-dashboard', 'data-base-target' => '_next');

    /** @var Storage */
    protected $storage;

    /**
     * Dashboard constructor.
     * @param Storage $storage
     */
    protected function __construct(Storage $storage)
    {
        $this->storage = $storage;
        $processes = $storage->listProcessNames();
        if (empty($processes)) {
            $this->add(Html::tag(
                'div',
                [
                    Html::tag('h1', mt('businessprocess', 'Not available')),
                    Html::tag('p', mt('businessprocess', 'No Business Process has been defined for you'))
                ]
            ));
        }

        foreach ($processes as $name) {
            $meta = $storage->loadMetadata($name);
            $title = $meta->get('Title');

            if ($title === null) {
                $title = $name;
            }

            try {
                $bp = $storage->loadProcess($name);
            } catch (Exception $e) {
                $this->add(new BpDashboardTile(
                    new BpConfig(),
                    $title,
                    sprintf(t('File %s has faulty config'), $name . '.conf'),
                    'file-circle-xmark',
                    'businessprocess/process/show',
                    ['config' => $name]
                ));

                continue;
            }

            if (Module::exists('icingadb') &&
                (! $bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend())
            ) {
                IcingaDbState::apply($bp);
            } else {
                MonitoringState::apply($bp);
            }

            $this->add(new BpDashboardFullscreenTile(
                $bp,
                $title,
                $meta->get('Description'),
                'sitemap',
                'businessprocess/process/show',
                array('config' => $name)
            ));
        }
    }

    /**
     * @param Auth $auth
     * @param Storage $storage
     * @return static
     */
    public static function create(Auth $auth, Storage $storage)
    {
        return new static($storage);
    }
}
