# HEX_FixImageResizeQueue

Corrige falhas do consumer `media.storage.catalog.image.resize` no **Admin** quando o Magento agenda o resize com um filename “antigo” (pré-move) e o arquivo é renomeado ao mover de `pub/media/tmp/...` para `pub/media/catalog/product/...`.

## Sintoma

- Erro no consumer/fila: `Cannot resize image ".../pub/media/catalog/product/...jpg" - original image not found`

## Como funciona

- Plugin **adminhtml** em `Magento\Catalog\Observer\ImageResizeAfterProductSave`.
- Recarrega o produto persistido e agenda o resize usando os filenames finais do DB (`getMediaGalleryImages()`), evitando nomes “stale”.

## Instalação

- `php bin/magento module:enable HEX_FixImageResizeQueue`
- `php bin/magento setup:upgrade`
- `php bin/magento setup:di:compile`
- `php bin/magento cache:flush`

