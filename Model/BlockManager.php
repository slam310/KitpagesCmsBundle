<?php
namespace Kitpages\CmsBundle\Model;

use Kitpages\CmsBundle\Entity\Block;
use Kitpages\CmsBundle\Entity\BlockPublish;
use Kitpages\CmsBundle\Event\BlockEvent;
use Kitpages\CmsBundle\KitpagesCmsEvents;
use Kitpages\FileBundle\Entity\File;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Bundle\DoctrineBundle\Registry;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class BlockManager
{
 
    ////
    // dependency injection
    ////
    protected $dispatcher = null;
    protected $doctrine = null;
    protected $templating = null;
    protected $fileManager = null;
    
    public function __construct(Registry $doctrine, EventDispatcher $dispatcher, $templating, CmsFileManager $fileManager){
        $this->dispatcher = $dispatcher;
        $this->doctrine = $doctrine;
        $this->templating = $templating;
        $this->fileManager = $fileManager;        
    }      

    /**
     * @return EventDispatcher $dispatcher
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }  
    
    /**
     * @return $templating
     */
    public function getTemplating() {
        return $this->templating;
    }    
    
    /**
     * @return Registry $doctrine
     */
    public function getDoctrine() {
        return $this->doctrine;
    }

    /**
     * @return $fileManager
     */
    public function getFileManager() {
        return $this->fileManager;
    }    
    
    ////
    // action function
    ////
    /**
     *
     * @param Block $block 
     */
    public function delete(Block $block)
    {
        // throw on event
        $fileManager = $this->getFileManager();
        $event = new BlockEvent($block);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onBlockDelete, $event);
        
        // preventable action
        if (!$event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            $fileManager->deleteInBlockData($block->getData());
            $em->remove($block);
            $em->flush();
        }
        // throw after event
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterBlockDelete, $event);
    }

    
    public function deletePublished(BlockPublish $blockPublish)
    {
        $fileManager = $this->getFileManager();
        $data = $blockPublish->getData();
        $fileManager->deletePublishedInBlockData($data['media']);
        $em = $this->getDoctrine()->getEntityManager();
        $em->remove($blockPublish);
    }
    
    public function render($templateTwig, $blockData, $publish, $listMediaUrl = null) {
        $fileManager = $this->getFileManager();
        if (is_null($listMediaUrl)) {
            $listMediaUrl = $fileManager->urlListInBlockData($blockData, $publish);
        }
        $blockData['root'] = array_merge($blockData['root'], $listMediaUrl);

        return $this->getTemplating()->render(
            $templateTwig,
            array('data' => $blockData)
        );  
    }
    
    public function publish(Block $block, array $listRenderer)
    {
        $event = new BlockEvent($block, $listRenderer);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onBlockPublish, $event);
        $fileManager = $this->getFileManager();
        if (!$event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            $query = $em->createQuery("
                SELECT bp FROM KitpagesCmsBundle:BlockPublish bp
                WHERE bp.block = :block
            ")->setParameter('block', $block);
            $blockPublishList = $query->getResult();

            foreach($blockPublishList as $blockPublish){
                $this->deletePublished($blockPublish);
            }
            $em->persist($block);
            $em->flush();
            $em->refresh($block);
            if ($block->getBlockType() == Block::BLOCK_TYPE_EDITO) {
                $blockData = $block->getData();
                echo var_dump($blockData);
                if (!is_null($blockData) && isset($blockData['root'])) {             
                    foreach($listRenderer as $nameRenderer => $renderer) {
                        
                        $fileManager->publishInBlockData($blockData);
                        
                        $listMediaUrl = $fileManager->urlListInBlockData($blockData, true);
                        $blockData['root'] = array_merge($blockData['root'], $listMediaUrl);
                      
                        $resultingHtml = $this->render($renderer['twig'], $blockData, true);
                        

                        $blockPublish = new BlockPublish();
                        $blockPublish->initByBlock($block);
                        $blockPublish->setData(array("html"=>$resultingHtml, "media" => $listMediaUrl));
                        $blockPublish->setRenderer($nameRenderer);
                        $em->persist($blockPublish);
                    }
                }
            }
            $block->setIsPublished(true);
            $em->persist($block);
            $em->flush();
        }
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterBlockPublish, $event);
    }
    
    public function afterModify($block, $oldBlockData)
    {
        if ($oldBlockData != $block->getData()) {
            $block->setRealUpdatedAt(new \DateTime());
            $block->setIsPublished(false);
            $em = $this->getDoctrine()->getEntityManager();
            $em->flush();
            $event = new BlockEvent($block);
            $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterBlockModify, $event);
        }
    }

    ////
    // doctrine events
    ////
    public function prePersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof Block) {
            $blockSlug = $entity->getSlug();
            if(empty($blockSlug)) {
                $entity->setSlug('block_ID');
            }
        }
    }
    public function postPersist(LifecycleEventArgs $event)
    {    
        /* Event BLOCK */
        $entity = $event->getEntity();
        if ($event->getEntity() instanceof Block) {
            if($entity->getSlug() == 'block_ID') {
                $entity->defaultSlug();
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($entity);
                $em->flush();
            }
        }    
    }
    
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        $em = $eventArgs->getEntityManager();
//        $uom = $em->getUnitOfWork();
        
        /* Event BLOCK */
        if ($entity instanceof Block) {
            $blockSlug = $entity->getSlug();
            if(empty($blockSlug)) {
                $entity->defaultSlug();
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($entity);
                $em->flush();
            }
            
//            if ($eventArgs->hasChangedField('data')) {
//                $entity->setRealUpdatedAt(new \DateTime());
//                $entity->setIsPublished(false);
//                if ($entity->getIsPublished() == 1) {
//                    $entity->setUnpublishedAt(new \DateTime());
//                }
//                $uom->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($entity)), $entity);
//            }
           
        }
    }         
    
}
