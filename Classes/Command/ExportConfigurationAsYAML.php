<?php
namespace EWW\Dpf\Command;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use EWW\Dpf\Domain\Model\Client;
use EWW\Dpf\Services\ElasticSearch\ElasticSearch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use EWW\Dpf\Domain\Repository\MetadataPageRepository;
use EWW\Dpf\Domain\Repository\MetadataGroupRepository;
use EWW\Dpf\Domain\Repository\MetadataObjectRepository;

/**
 * Class ExportConfigurationAsYAML
 *
 * A console command to import Metadata from yml file to the TYPO3 database.
 * Usage: vendor/bin/typo3 dpf:exportConfigurationAsYAML <client> > <outputfile>
 *
 * @package EWW\Dpf\Command
 */
class ExportConfigurationAsYAML extends AbstractIndexCommand
{
    /**
     * Configure the command by defining arguments
     */

    protected function configure()
    {
        $this->setDescription('Export form configuration for the given client');
        $this->addArgument('client', InputArgument::REQUIRED, 'The UID of the client.');
    }

    /**
     * Executes the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $clientUid = $input->getArgument('client');

        /** @var Client $client */
        $client = $this->clientRepository->findByUid($clientUid);

        if ($client) {
            // Set the client storagePid
            Client::$storagePid = $client->getPid();
            
            $export = array('metadataObject'=>[],
                            'metadataGroup'=>[],
                            'metadataPage' =>[],
                            'documentType'=>[]
                            );            
            
            $metadataObjectRepository = $this->objectManager->get(MetadataObjectRepository::class);
            $metadataObjectRepository->setStoragePid(Client::$storagePid);
            foreach ($metadataObjectRepository->findAll() as $metadataObject) {
                $export['metadataObject'][$metadataObject->getUid()] = (array) $metadataObject->_getProperties();
            }
            
            $metadataGroupRepository = $this->objectManager->get(MetadataGroupRepository::class);
            $metadataGroupRepository->setStoragePid(Client::$storagePid);
            foreach ($metadataGroupRepository->findAll() as $metadataGroup) {
                $export['metadataGroup'][$metadataGroup->getUid()] = (array) $metadataGroup->_getProperties();

                $metadataObject = $metadataGroup->getMetadataObject()->toArray();
                $metadataObjectIds = array_map(function($obj){ return $obj->getUid(); }, $metadataObject);
                $export['metadataGroup'][$metadataGroup->getUid()]['metadataObjects'] = $metadataObjectIds;
            }
            
            $metadataPageRepository = $this->objectManager->get(MetadataPageRepository::class);
            $metadataPageRepository->setStoragePid(Client::$storagePid);
            foreach ($metadataPageRepository->findAll() as $metadataPage) {
                $export['metadataPage'][$metadataPage->getUid()] = (array) $metadataPage->_getProperties();
                $metadataGroup = $metadataPage->getMetadataGroup()->toArray();
                $metadataGroupIds = array_map(function($obj){ return $obj->getUid(); }, $metadataGroup);
                $export['metadataPage'][$metadataPage->getUid()]['metadataGroups'] = $metadataGroupIds;
            }
            
            $this->documentTypeRepository->setStoragePid(Client::$storagePid);
            foreach ($this->documentTypeRepository->findAll() as $documentType) {
                $export['documentType'][$documentType->getUid()] = (array) $documentType->_getProperties();
                $metadataPages = $documentType->getMetadataPage()->toArray();
                $metadataPagesIds = array_map(function($obj){ return $obj->getUid(); }, $metadataPages);
                $export['documentType'][$documentType->getUid()]['metadataPages'] = $metadataPagesIds;
            }

            $ymlString = Yaml::dump($export,4,4,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            $io->writeln($ymlString);
            return true;
        } else {
            $io->title("Exporting documentTypes: '" . $client->getClient() . "'");
            $error = "Unknown client '" . $clientUid ."'";
        }
        $io->title("Exporting documentTypes: '" . $client->getClient() . "'");
        $io->write('Failed: ');
        $io->writeln($error);
        $io->writeln('');

        return false;
    }
}
