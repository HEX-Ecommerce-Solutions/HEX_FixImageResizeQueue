<?php
declare(strict_types=1);

namespace HEX\FixImageResizeQueue\Plugin\Catalog\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Config\CatalogMediaConfig;
use Magento\Framework\Event\Observer;
use Magento\MediaStorage\Service\ImageResizeScheduler;

final class ImageResizeAfterProductSavePlugin
{
    public function __construct(
        private readonly CatalogMediaConfig $catalogMediaConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageResizeScheduler $imageResizeScheduler
    ) {
    }

    /**
     * Adminhtml runs image resize asynchronously via queue. When the uploaded image is renamed during
     * tmp->catalog move (e.g. to avoid filename collision), the core observer may enqueue the pre-move
     * filename. This plugin enqueues only the persisted filenames from DB after save.
     */
    public function aroundExecute(
        \Magento\Catalog\Observer\ImageResizeAfterProductSave $subject,
        callable $proceed,
        Observer $observer
    ): void {
        $catalogMediaUrlFormat = $this->catalogMediaConfig->getMediaUrlFormat();
        if ($catalogMediaUrlFormat === CatalogMediaConfig::IMAGE_OPTIMIZATION_PARAMETERS) {
            // Core behavior: skip resizing when offloaded to web server/CDN
            return;
        }

        $event = $observer->getEvent();
        $product = $event ? $event->getProduct() : null;
        if (!$product || !(int) $product->getId()) {
            $proceed($observer);
            return;
        }

        $original = $product->getOrigData('media_gallery');
        $oldFiles = !empty($original['images']) ? array_column($original['images'], 'file') : [];
        $oldFiles = array_values(array_filter(array_map('strval', $oldFiles)));

        $storeId = (int) $product->getStoreId();
        $reloadedProduct = $this->productRepository->getById((int) $product->getId(), false, $storeId);

        $finalFiles = [];
        foreach ($reloadedProduct->getMediaGalleryImages() as $image) {
            $finalFiles[] = (string) $image->getFile();
        }
        $finalFiles = array_values(array_filter($finalFiles));

        foreach (array_diff($finalFiles, $oldFiles) as $file) {
            $this->imageResizeScheduler->schedule($file);
        }
    }
}
