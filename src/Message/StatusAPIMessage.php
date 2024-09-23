<?php

namespace App\Message;

class StatusAPIMessage {
    private $userId;
    private $ciUploadId;
    private $documentId;
    const SCAN = 1;
    const STATUS = 2;

    public function __construct($userId, $ciUploadId, $documentId){
        $this->userId = $userId;
        $this->ciUploadId = $ciUploadId;
        $this->documentId = $documentId;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     */
    public function setUserId($userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return mixed
     */
    public function getCiUploadId()
    {
        return $this->ciUploadId;
    }

    /**
     * @param mixed $ciUploadId
     */
    public function setCiUploadId($ciUploadId): void
    {
        $this->ciUploadId = $ciUploadId;
    }

    /**
     * @return mixed
     */
    public function getDocumentId()
    {
        return $this->documentId;
    }

    /**
     * @param mixed $documentId
     */
    public function setDocumentId($documentId): void
    {
        $this->documentId = $documentId;
    }

}