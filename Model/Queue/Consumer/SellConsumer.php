<?php

/**
 * @author Mygento Team
 * @copyright 2017-2020 Mygento (https://www.mygento.ru)
 * @package Mygento_Kkm
 */

namespace Mygento\Kkm\Model\Queue\Consumer;

use Mygento\Kkm\Api\Processor\SendInterface;
use Mygento\Kkm\Exception\VendorBadServerAnswerException;
use Mygento\Kkm\Exception\VendorNonFatalErrorException;

class SellConsumer extends AbstractConsumer
{
    /**
     * @param \Mygento\Kkm\Api\Queue\MergedRequestInterface $mergedRequest
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function sendMergedRequest($mergedRequest)
    {
        $requests = $mergedRequest->getRequests();
        $this->helper->debug(count($requests) . ' SellRequests received to process.');
        foreach ($requests as $request) {
            $this->sendSellRequest($request);
        }
    }

    /**
     * @param \Mygento\Kkm\Api\Data\RequestInterface $request
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function sendSellRequest($request)
    {
        try {
            $this->vendor->sendSellRequest($request);
        } catch (VendorNonFatalErrorException $e) {
            // меняем external_id и пробуем сделать повторную отправку
            $this->helper->info($e->getMessage());

            $request->setIgnoreTrialsNum(false);
            $this->increaseExternalId($request);
            $this->publisher->publish(SendInterface::TOPIC_NAME_SELL, $request);
        } catch (VendorBadServerAnswerException $e) {
            $this->helper->info($e->getMessage());

            $storeId = $request->getStoreId();
            if ($this->helper->isUseCustomRetryIntervals($storeId)) {
                // помечаем заказ, как KKM Fail
                // далее находим попытку, ставим флаг is_scheduled и заполняем время scheduled_at
                $entity = $this->requestHelper->getEntityByRequest($request);
                $this->errorHelper->processKkmChequeRegistrationError($entity, $e);
                $this->attemptHelper->scheduleNextAttempt($request, SendInterface::TOPIC_NAME_SELL);
            } else {
                $request->setIgnoreTrialsNum(false);
                $this->publisher->publish(SendInterface::TOPIC_NAME_SELL, $request);
            }
        } catch (\Throwable $e) {
            $entity = $this->requestHelper->getEntityByRequest($request);
            $this->errorHelper->processKkmChequeRegistrationError($entity, $e);
            if ($this->helper->isRetrySendingEndlessly($entity->getStoreId())) {
                // находим попытку, ставим флаг is_scheduled и заполняем время scheduled_at на следующей день
                $this->attemptHelper->scheduleNextAttempt(
                    $request,
                    SendInterface::TOPIC_NAME_SELL,
                    (new \DateTime('+1 day'))->format('Y-m-d H:i:s')
                );
            }
        }
    }
}
