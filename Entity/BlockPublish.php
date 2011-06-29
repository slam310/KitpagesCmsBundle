<?php

namespace Kitpages\CmsBundle\Entity;

use Kitpages\CmsBundle\Entity\Block;
/**
 * Kitpages\CmsBundle\Entity\Block
 */
class BlockPublish
{
    /**
     * @var string $slug
     */
    private $slug;

    /**
     * @var string $blockType
     */
    private $blockType;

    /**
     * @var array $data
     */
    private $data;

    /**
     * @var datetime $createdAt
     */
    private $createdAt;

    /**
     * @var integer $id
     */
    private $id;


    /**
     * Set label
     *
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * Get label
     *
     * @return string $label
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set blockType
     *
     * @param string $blockType
     */
    public function setBlockType($blockType)
    {
        $this->blockType = $blockType;
    }

    /**
     * Get blockType
     *
     * @return string $blockType
     */
    public function getBlockType()
    {
        return $this->blockType;
    }

    /**
     * Set data
     *
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return array $data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set creationDate
     *
     * @param datetime $creationDate
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;
    }

    /**
     * Get creationDate
     *
     * @return datetime $creationDate
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Get id
     *
     * @return integer $id
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @ORM\prePersist
     */
    public function prePersist()
    {
        // Add your code here
    }

    /**
     * @ORM\preUpdate
     */
    public function preUpdate()
    {
        // Add your code here
    }

    /**
     * Set slug
     *
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    /**
     * Get slug
     *
     * @return string $slug
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set createdAt
     *
     * @param datetime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Get createdAt
     *
     * @return datetime $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    
    public function initByBlock(Block $block) {
        $this->setSlug($block->getSlug());
        $this->setBlockType($block->getBlockType());
        $this->setBlock($block);
    }
    /**
     * @var Kitpages\CmsBundle\Entity\Block
     */
    private $block_id;


    /**
     * Set block_id
     *
     * @param Kitpages\CmsBundle\Entity\Block $blockId
     */
    public function setBlockId(\Kitpages\CmsBundle\Entity\Block $blockId)
    {
        $this->block_id = $blockId;
    }

    /**
     * Get block_id
     *
     * @return Kitpages\CmsBundle\Entity\Block $blockId
     */
    public function getBlockId()
    {
        return $this->block_id;
    }
    /**
     * @var Kitpages\CmsBundle\Entity\Block
     */
    private $block;


    /**
     * Set block
     *
     * @param Kitpages\CmsBundle\Entity\Block $block
     */
    public function setBlock(\Kitpages\CmsBundle\Entity\Block $block)
    {
        $this->block = $block;
    }

    /**
     * Get block
     *
     * @return Kitpages\CmsBundle\Entity\Block $block
     */
    public function getBlock()
    {
        return $this->block;
    }
}