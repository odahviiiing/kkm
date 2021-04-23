<?php


namespace Mygento\Kkm\Model\CheckOnline;


use Mygento\Kkm\Api\Data\ItemInterface;
use Mygento\Kkm\Api\Data\RequestInterface;

class Request extends \Mygento\Kkm\Model\Request\Request
{
    const REQUEST_ID_KEY = 'RequestId';

    const CHECKONLINE_OPERATION_TYPE_MAPPING = [
        RequestInterface::SELL_OPERATION_TYPE => 0,
        RequestInterface::REFUND_OPERATION_TYPE => 2,
        RequestInterface::RESELL_REFUND_OPERATION_TYPE => 2,
        RequestInterface::RESELL_SELL_OPERATION_TYPE => 0,
    ];

    /**
     * @var string
     */
    private $device = 'auto';

    /**
     * @var int
     */
    private $password = 1;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var int[]
     */
    private $nonCash;

    /**
     * @var string
     */
    private $place;

    /**
     * @var string
     */
    private $group;

    /**
     * @var bool
     */
    private $fullResponse;

    /**
     * @var string
     */
    private $entityType;

    /**
     * @var int
     */
    private $advancePayment;

    /**
     * @inheritDoc
     */
    public function getSno()
    {
        return (int) $this->sno;
    }

    /**
     * @return string
     */
    public function getDevice(): string
    {
        return $this->device;
    }

    /**
     * @return int
     */
    public function getPassword(): int
    {
        return $this->password;
    }

    /**
     * @return string|null
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param $clientId
     * @return RequestInterface
     */
    public function setClientId($clientId): RequestInterface
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getNonCash(): array
    {
        return $this->nonCash;
    }

    /**
     * @param array $nonCash
     * @return RequestInterface
     */
    public function setNonCash($nonCash): RequestInterface
    {
        $this->nonCash = $nonCash;

        return $this;
    }

    /**
     * @return string
     */
    public function getPlace(): string
    {
        return $this->place;
    }

    /**
     * @param string $place
     * @return RequestInterface
     */
    public function setPlace($place): RequestInterface
    {
        $this->place = $place;

        return $this;
    }

    /**
     * @return string|int
     */
    public function getEntityStoreId()
    {
        return $this->entityStoreId;
    }

    /**
     * @param string|int $storeId
     * @return RequestInterface
     */
    public function setEntityStoreId($storeId): RequestInterface
    {
        $this->entityStoreId = $storeId;

        return $this;
    }

    /**
     * @return string
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @param string $group
     * @return RequestInterface
     */
    public function setGroup($group): RequestInterface
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return bool
     */
    public function getFullResponse(): bool
    {
        return $this->fullResponse;
    }

    /**
     * @param bool $fullResponse
     * @return RequestInterface
     */
    public function setFullResponse($fullResponse): RequestInterface
    {
        $this->fullResponse = $fullResponse;

        return $this;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @param $entityType
     * @return RequestInterface
     */
    public function setEntityType($entityType): RequestInterface
    {
        $this->entityType = $entityType;

        return $this;
    }

    /**
     * @return int
     */
    public function getAdvancePayment(): int
    {
        return $this->advancePayment;
    }

    /**
     * @param int $advancePayment
     * @return RequestInterface
     */
    public function setAdvancePayment($advancePayment): RequestInterface
    {
        $this->advancePayment = $advancePayment;

        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = [
            'Device' => $this->getDevice(),
            'Password' => $this->getPassword(),
            'RequestId' => $this->getExternalId(),
            'DocumentType' => self::CHECKONLINE_OPERATION_TYPE_MAPPING[$this->getOperationType()],
            'Lines' => $this->getItems(),
            'NonCash' => $this->getNonCash(),
            'TaxMode' => $this->getSno(),
            'PhoneOrEmail' => $this->getPhone() ?: $this->getEmail(),
            'Place' => $this->getPlace(),
            'FullResponse' => $this->getFullResponse(),
        ];

        if ($this->getAdvancePayment() > 0) {
            $data['AdvancePayment'] = $this->getAdvancePayment();
        }

        if ($this->getClientId()) {
            $data['ClientId'] = $this->getClientId();
        }

        if ($this->getGroup()) {
            $data['Group'] = $this->getGroup();
        }

        return $data;
    }
}
