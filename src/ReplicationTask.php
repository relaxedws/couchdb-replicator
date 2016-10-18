<?php

namespace Relaxed\Replicator;

class ReplicationTask
{

    /**
     * @var null
     */
    protected $repId;

    /**
     * @var bool
     */
    protected $continuous;

    /**
     * @var string
     */
    protected $filter;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var bool
     */
    protected $createTarget;

    /**
     * @var array
     */
    protected $docIds;

    /**
     * @var int
     */
    protected $heartbeat;

    /**
     * @var
     */
    protected $timeout;

    /**
     * @var
     */
    protected $cancel;

    /**
     * @var string
     */
    protected $style;

    /**
     * @var int
     */
    protected $sinceSeq;

    /**
     * @param null $repId
     * @param bool $continuous
     * @param null $filter
     * @param array $parameters
     * @param bool $createTarget
     * @param array $docIds
     * @param int $heartbeat
     * @param int $timeout
     * @param bool $cancel
     * @param string $style
     * @param int $sinceSeq
     */
    public function __construct(
        $repId = null,
        $continuous = false,
        $filter = null,
        array $parameters = null,
        $createTarget = false,
        array $docIds = null,
        $heartbeat = 10000,
        $timeout = 10000,
        $cancel = false,
        $style = "all_docs",
        $sinceSeq = 0

    ) {
        $this->repId = $repId;
        $this->continuous = $continuous;
        $this->filter = $filter;
        $this->parameters = $parameters;
        $this->createTarget = $createTarget;
        $this->docIds = $docIds;
        $this->heartbeat = $heartbeat;
        $this->timeout = $timeout;
        $this->cancel = $cancel;
        $this->style = $style;
        $this->sinceSeq = $sinceSeq;

        if ($docIds != null) {
            \sort($this->docIds);
            if ($filter == null) {
                $this->filter = '_doc_ids';
            }
            elseif ($filter !== '_doc_ids') {
                throw new \InvalidArgumentException('If docIds is specified,
                the filter should be set as _doc_ids');
            }
        }
    }

    /**
     * @return mixed
     */
    public function getRepId()
    {
        return $this->repId;
    }

    /**
     * @param mixed $repId
     */
    public function setRepId($repId)
    {
        $this->repId = $repId;
    }

    /**
     * @return bool
     */
    public function getContinuous()
    {
        return $this->continuous;
    }

    /**
     * @param bool $continuous
     */
    public function setContinuous($continuous)
    {
        $this->continuous = $continuous;
    }

    /**
     * @return string
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param string $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array|NULL $parameters
     *   An associative array of name-value parameters.
     */
    public function setParameters(array $parameters = NULL) {
        if ($parameters == NULL) {
            $parameters = [];
        }
        $this->parameters = $parameters;
    }

    /**
     * @param string $name
     *   The parameter name to set.
     * @param string $value
     *   The value for the parameter.
     */
    public function setParameter($name, $value) {
        if (!is_array($this->parameters)) {
            $this->setParameters([]);
        }
        $this->parameters[$name] = $value;
    }

    /**
     * @return boolean
     */
    public function getCreateTarget()
    {
        return $this->createTarget;
    }

    /**
     * @param boolean $createTarget
     */
    public function setCreateTarget($createTarget)
    {
        $this->createTarget = $createTarget;
    }

    /**
     * @return array
     */
    public function getDocIds()
    {
        return $this->docIds;
    }

    /**
     * @param array $docIds
     */
    public function setDocIds($docIds)
    {
        if ($docIds != null) {
            \sort($docIds);
            if ($this->filter == null) {
                $this->filter = '_doc_ids';
            }
            elseif ($this->filter !== '_doc_ids') {
                throw new \InvalidArgumentException('If docIds is specified,
                the filter should be set as _doc_ids');
            }
        }
        $this->docIds = $docIds;
    }

    /**
     * @return int
     */
    public function getHeartbeat()
    {
        return $this->heartbeat;
    }

    /**
     * @param int $heartbeat
     */
    public function setHeartbeat($heartbeat)
    {
        $this->heartbeat = $heartbeat;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return mixed
     */
    public function getCancel()
    {
        return $this->cancel;
    }

    /**
     * @param mixed $cancel
     */
    public function setCancel($cancel)
    {
        $this->cancel = $cancel;
    }

    /**
     * @return mixed
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * @param mixed $style
     */
    public function setStyle($style)
    {
        $this->style = $style;
    }

    /**
     * @return mixed
     */
    public function getSinceSeq()
    {
        return $this->sinceSeq;
    }

    /**
     * @param mixed $sinceSeq
     */
    public function setSinceSeq($sinceSeq)
    {
        $this->sinceSeq = $sinceSeq;
    }

}
