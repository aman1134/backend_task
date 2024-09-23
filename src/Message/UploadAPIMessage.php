<?php

namespace App\Message;

class UploadAPIMessage {
    private $userId;
    private $productId;
    private $docId;

    public function __construct($userId, $productId, $docId){
        $this->docId = $docId;
        $this->userId = $userId;
        $this->productId = $productId;
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
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @param mixed $productId
     */
    public function setProductId($productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return mixed
     */
    public function getDocId()
    {
        return $this->docId;
    }

    /**
     * @param mixed $docId
     */
    public function setDocId($docId): void
    {
        $this->docId = $docId;
    }


}