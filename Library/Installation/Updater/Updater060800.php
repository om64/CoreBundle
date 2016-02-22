<?php
/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Library\Installation\Updater;

use Claroline\CoreBundle\Entity\Tool\AdminTool;
use Claroline\InstallationBundle\Updater\Updater;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Updater060800 extends Updater
{
    private $container;
    private $om;
    private $adminToolRepo;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->om = $container->get('claroline.persistence.object_manager');
        $this->adminToolRepo = $this->om->getRepository('ClarolineCoreBundle:Tool\AdminTool');
    }

    public function postUpdate()
    {
        $this->createOrganizationTool();
        $this->removePackageTool();
        $this->removeBundleTable();
        $this->container->get('claroline.manager.administration_manager')->addDefaultUserAdminActions();
    }

    private function createOrganizationTool()
    {
        if (!$this->adminToolRepo->findOneByName('organization_management')) {
            $this->log('Creating institution admin tool...');
            $entity = new AdminTool();
            $entity->setName('organization_management');
            $entity->setClass('institution');
            $this->om->persist($entity);
            $this->om->flush();
        }
    }

    private function removePackageTool()
    {
        if ($tool = $this->adminToolRepo->findOneByName('platform_packages')) {
            $this->log('Removing package management admin tool...');
            $this->om->remove($tool);
            $this->om->flush();
        }
    }

    private function removeBundleTable()
    {
        $schema = $this->container->get('doctrine.dbal.default_connection')->getSchemaManager();

        if ($schema->tablesExist('claro_bundle')) {
            $this->log('Removing bundle table...');
            $schema->dropTable('claro_bundle');
        }
    }
}
