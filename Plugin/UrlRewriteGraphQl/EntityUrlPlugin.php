<?php
namespace StorefrontX\BlogGraphQlExtended\Plugin\UrlRewriteGraphQl;

use Amasty\Blog\Model\Repository\CategoriesRepository;
use Amasty\Blog\Model\Repository\PostRepository;
use Amasty\Blog\Model\Repository\TagRepository;
use Amasty\Blog\Helper\Settings as AmastyBlogSettings;
use Amasty\Blog\Model\UrlResolver as AmastyUrlResolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\UrlRewriteGraphQl\Model\Resolver\EntityUrl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;

/**
 * Class EntityUrlPlugin
 * @package  StorefrontX\BlogGraphQlExtended\Plugin\UrlRewriteGraphQl
 */
class EntityUrlPlugin
{
    /** @var string  */
    public const AMASTY_BLOG_CATEGORY = 'AMASTY_BLOG_CATEGORY';

    /** @var string  */
    public const AMASTY_BLOG_POST = 'AMASTY_BLOG_POST';


    /** @var string  */
    public const AMASTY_BLOG_TAG = 'AMASTY_BLOG_TAG';

    /** @var string  */
    public const CATEGORY_URL_PREFIX = 'category';

    /** @var string  */
    public const TAG_URL_PREFIX = 'tag';

    /** @var int  */
    public const CATEGORY_URL_PREFIX_LENTGH = 8;

    /** @var int  */
    public const REDIRECT_301 = 301;

    /** @var string  */
    public const REDIRECT_301_RESPONSE_TYPE = 'REDIRECT_301';

    /** @var PostRepository */
    public $postRepository;


    /** @var CategoriesRepository */
    public $categoriesRepository;

    /** @var TagRepository */
    public $tagRepository;

    /** @var AmastyBlogSettings  */
    public $amastyBlogSettings;

    /** @var AmastyUrlResolver  */
    public $amastyUrlResolver;

    /** @var UrlRewriteCollectionFactory  */
    protected $urlRewriteCollectionFactory;

    /** @var StoreManagerInterface  */
    protected $storeManager;

    /**
     * Class construct
     *
     * @param CategoriesRepository $categoriesRepository
     * @param PostRepository $postRepository
     * @param TagRepository $tagRepository
     * @param UrlRewriteCollectionFactory $urlRewriteCollectionFactory
     * @param AmastyBlogSettings $amastyBlogSettings
     * @param AmastyUrlResolver $amastyUrlResolver
     * @param StoreManagerInterface $StoreManager
     */
    public function __construct(
        CategoriesRepository $categoriesRepository,
        PostRepository $postRepository,
        TagRepository $tagRepository,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        AmastyBlogSettings $amastyBlogSettings,
        AmastyUrlResolver $amastyUrlResolver,
        StoreManagerInterface $StoreManager
    )
    {
        $this->postRepository = $postRepository;
        $this->categoriesRepository = $categoriesRepository;
        $this->tagRepository = $tagRepository;
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->amastyBlogSettings = $amastyBlogSettings;
        $this->amastyUrlResolver = $amastyUrlResolver;
        $this->storeManager = $StoreManager;
    }

    /**
     * Clear $_GET param from slug before resolve
     *
     * @param EntityUrl $subject
     * @param array $args
     */
    public function beforeResolve(EntityUrl $subject, ...$args) {
        $url = $args[4]['url'] ?? '';
        if ($url && strpos($url, '?') !== false) {
            $explodedUrl = explode('?', $url);
            $args[4]['url'] = reset($explodedUrl);
        }
        return $args;
    }



    /**
     *
     * In case UrlResolver returns empty response - checks if blog post urls exists.
     * Changing response for urls resolver with blog results.
     *
     * @param EntityUrl $subject
     * @param $result
     * @param array $args
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterResolve(EntityUrl $subject, $result, ...$args)
    {
        $url = $args[4]['url'] ?? '';

        $emptyResult = [ null ];

        $existingRedirect = $this->checkRedirectResponse($url);
        if ($existingRedirect && isset($existingRedirect['redirect_type'])) {
            $baseUrl = $this->getBaseUrl();

            $result['id'] = $existingRedirect['url_rewrite_id'];
            $result['relative_url'] = $existingRedirect['target_path']; // Target path is final url where we need to redirect
            $result['canonical_url'] = $baseUrl . $existingRedirect['target_path'];
            $result['type'] = self::REDIRECT_301_RESPONSE_TYPE;
            $result['redirectCode'] = self::REDIRECT_301;
        } else {
            list($urlKey, $preUrlKey) = $this->getUrlKeyAndPrefix($url);

            if ($preUrlKey) {
                if ($preUrlKey == self::TAG_URL_PREFIX) {
                    $tag = $this->tagRepository->getByUrlKey($urlKey);
                    if ($tag->getTagId()) {
                        $result['id'] = $tag->getId();
                        $result['type'] = self::AMASTY_BLOG_TAG;
                        $result['relative_url'] = $tag->getUrl();
                    }
                } else if($preUrlKey == self::CATEGORY_URL_PREFIX) {
                    $category = $this->categoriesRepository->getByUrlKey($urlKey);
                    if ($category->getCategoryId()) {
                        $result['id'] = $category->getId();
                        $result['type'] = self::AMASTY_BLOG_CATEGORY;
                        $result['relative_url'] = $category->getUrl();
                    }
                }
            } else {
                $post = $this->postRepository->getByUrlKey($urlKey);
                if ($post->getId()) {
                    $result['id'] = $post->getId();
                    $result['type'] = self::AMASTY_BLOG_POST;
                    $result['relative_url'] = $post->getUrl();
                }
            }
        }
        if (!isset($result) || !$result) {
            $result = $emptyResult;
        }
        return $result;
    }


    /**
     * Gets url key of entitny and possible prefix like /tag/ /category/
     *
     * @param string $url
     * @return array
     *
     */
    public function getUrlKeyAndPrefix(string $url): array {

        $cleanUrl = trim($url, '/');

        $postfix = $this->amastyBlogSettings->getBlogPostfix();
        // Remove postfix
        if ($postfix && $postfixCut = mb_strrchr($cleanUrl, $postfix, true)) {
            $cleanUrl = $postfixCut;
        }
        // Remove base url
        $baseUrl = $this->getBaseUrl();
        if ($baseUrl && $baseUrl ==  mb_substr($cleanUrl, 0, strlen($baseUrl))) {
            $cleanUrl = trim(mb_substr($cleanUrl, strlen($baseUrl)), '/');
        }
        // Remove blog prefix
        $route = $this->amastyBlogSettings->getSeoRoute();
        if ($route && $route == mb_substr($cleanUrl, 0, strlen($route))) {
            $cleanUrl = trim(mb_substr($cleanUrl, strlen($route)), '/');
        }
        // Explode
        $explode = explode('/', $cleanUrl);
        $urlKey = end($explode);
        $preUrlKey = reset($explode);
        if($preUrlKey == $urlKey) {
            $preUrlKey = '';
        }

        return [$urlKey, $preUrlKey];
    }


    /**
     * Checks if this request url is redirected
     *
     * @param string $url
     *
     * @return array
     */
    public function checkRedirectResponse(string $url): array
    {
        $urlRewriteCollection = $this->urlRewriteCollectionFactory->create();
        $urlRewrite = $urlRewriteCollection->addFieldToSelect(['target_path', 'url_rewrite_id', 'redirect_type'])
            ->addFieldToFilter('request_path', ['eq' => $url])
            ->addFieldToFilter('redirect_type', ['eq' => self::REDIRECT_301])
            ->setPageSize(1)
            ->getFirstItem();
        return $urlRewrite->getData();
    }


    /**
     * Get base url of shop
     *
     * @retrun string
     */
    public function getBaseUrl(): string
    {
        /** Support for magento2 frontend */
        $storeId = $this->storeManager->getStore()->getId();
        return $this->storeManager->getStore($storeId)->getBaseUrl();
    }

}
