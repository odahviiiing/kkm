<?php

/**
 * @author Mygento Team
 * @copyright 2017-2020 Mygento (https://www.mygento.ru)
 * @package Mygento_Kkm
 */

namespace Mygento\Kkm\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Mygento\Kkm\Api\Processor\UpdateInterface;
use Mygento\Kkm\Helper\Transaction\Proxy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mygento\Kkm\Helper\Data;
use Mygento\Kkm\Helper\Request;

class UpdateStatus extends Command
{
    public const ARGUMENT = 'param';
    public const ARGUMENT_DESCRIPTION = 'UUID (Transaction id) or "all" to update all';
    public const COMMAND = 'mygento:kkm:update';
    public const COMMAND_DESCRIPTION = 'Get status from Atol and save it.';

    public const RUN_ALL_PARAM = 'all';

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * @var \Mygento\Kkm\Helper\Transaction\Proxy
     */
    private $transactionHelper;

    /**
     * @var \Mygento\Kkm\Api\Processor\UpdateInterface
     */
    private $updateProcessor;

    /**
     * @var \Mygento\Kkm\Helper\Data
     */
    private $kkmHelper;

    /**
     * @var \Mygento\Kkm\Helper\Request
     */
    private $requestHelper;

    /**
     * UpdateStatus constructor.
     * @param \Mygento\Kkm\Api\Processor\UpdateInterface $updateProcessor
     * @param \Mygento\Kkm\Helper\Transaction\Proxy $transactionHelper
     * @param \Magento\Framework\App\State $state
     * @param \Mygento\Kkm\Helper\Data $kkmHelper
     * @param \Mygento\Kkm\Helper\Request $requestHelper
     */
    public function __construct(
        UpdateInterface $updateProcessor,
        Proxy $transactionHelper,
        State $state,
        Data $kkmHelper,
        Request $requestHelper
    ) {
        parent::__construct();

        $this->appState = $state;
        $this->updateProcessor = $updateProcessor;
        $this->transactionHelper = $transactionHelper;
        $this->kkmHelper = $kkmHelper;
        $this->requestHelper = $requestHelper;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode(Area::AREA_GLOBAL);

        $param = $input->getArgument('param');

        $uuids = ($param === self::RUN_ALL_PARAM)
            ? $this->transactionHelper->getAllWaitUuids()
            : [$param];

        $i = 1;
        foreach ($uuids as $uuid) {
            $output->writeln("<comment>${i} Updating {$uuid} ...</comment>");
            $this->updateOne($output, $uuid);
            $i++;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setName(self::COMMAND);
        $this->setDescription(self::COMMAND_DESCRIPTION);
        $this->addArgument(
            self::ARGUMENT,
            InputArgument::REQUIRED,
            self::ARGUMENT_DESCRIPTION
        );
        $this->setHelp(
            <<<HELP
This command updates status of transaction.
To update one:
      <comment>%command.full_name% 290f5207-e555-402d-88b6-fcccab9a4024</comment>
To update all transaction with status 'wait':
      <comment>%command.full_name% </comment>
HELP
            . self::RUN_ALL_PARAM
        );
        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $uuid
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Mygento\Kkm\Exception\VendorBadServerAnswerException
     * @return int
     */
    private function updateOne($output, $uuid)
    {
        $entityStoreId = $this->getEntityStoreId($uuid);
        if (!$this->isNeedProcess($entityStoreId)) {
            $output->writeln(
                "<info>Vendor '{$this->kkmHelper->getCurrentVendorCode($entityStoreId)}'"
                . " does not need update status.</info>"
            );
            $output->writeln("<info>Store ID: {$entityStoreId}</info>");

            return Cli::RETURN_SUCCESS;
        }

        //Обновление статуса
        $response = $this->updateProcessor->proceedSync($uuid);

        if ($response->isFailed() || $response->getError()) {
            $output->writeln("<error>Status: {$response->getStatus()}</error>");
            $output->writeln("<error>Uuid: {$response->getIdForTransaction()}</error>");
            $output->writeln("<error>Text: {$response->getErrorMessage()}</error>");

            return Cli::RETURN_FAILURE;
        }

        $output->writeln("<info>Status: {$response->getStatus()}</info>");
        $output->writeln("<info>Uuid: {$response->getIdForTransaction()}</info>");

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param string $uuid
     * @return int|null
     * @throws \Exception
     */
    private function getEntityStoreId($uuid)
    {
        return $this->requestHelper->getEntityByUuid($uuid)->getStoreId();
    }

    /**
     * @param string|int|null $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\InvalidArgumentException
     */
    private function isNeedProcess($storeId)
    {
        return $this->kkmHelper->isVendorNeedUpdateStatus($storeId);
    }
}
