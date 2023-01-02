<?php
declare(strict_types=1);

namespace StorefrontX\BlogGraphQlExtended\Model;

use Amasty\Blog\Api\Data\GetPostRelatedProductsInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Amasty\Blog\Api\Data\AuthorInterface;
use Amasty\Blog\Api\AuthorRepositoryInterface;
use Amasty\Blog\Api\CategoryRepositoryInterface;
use Amasty\Blog\Api\TagRepositoryInterface;
use Amasty\Blog\Api\PostRepositoryInterface;
use Amasty\Blog\Api\ViewRepositoryInterface;
use Amasty\Blog\Api\CommentRepositoryInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Review\Model\ReviewFactory;

class Post
{
    /**
     * @var GetPostRelatedProductsInterface
     */
    private $getPostRelatedProducts;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * @var AuthorRepositoryInterface
     */
    private $authorRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var TagRepositoryInterface
     */
    private $tagRepository;

    /**
     * @var PostRepositoryInterface
     */
    protected $postRepository;

    /**
     * @var ViewRepositoryInterface
     */
    protected $viewRepository;

    /**
     * @var CommentRepositoryInterface
     */
    protected $commentRepository;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    public function __construct(
        GetPostRelatedProductsInterface $getPostRelatedProducts,
        ReviewFactory $reviewFactory,
        AuthorRepositoryInterface $authorRepository,
        CategoryRepositoryInterface $categoryRepository,
        TagRepositoryInterface $tagRepository,
        PostRepositoryInterface $postRepository,
        ViewRepositoryInterface $viewRepository,
        CommentRepositoryInterface $commentRepository,
        FilterProvider $filterProvider
    ) {
        $this->getPostRelatedProducts = $getPostRelatedProducts;
        $this->reviewFactory = $reviewFactory;
        $this->authorRepository = $authorRepository;
        $this->categoryRepository = $categoryRepository;
        $this->tagRepository = $tagRepository;
        $this->postRepository = $postRepository;
        $this->viewRepository = $viewRepository;
        $this->commentRepository = $commentRepository;
        $this->filterProvider = $filterProvider;
    }

    /**
     * get the post
     *
     * @param array|null $args
     * @return array
     */
    public function getPost(array $args = null):array
    {
        $result = [];

        if (isset($args['id']) || isset($args['urlKey'])) {

            // get authors
            $authorCollection = $this->authorRepository->getAuthorCollection();
            $authors = [];
            foreach ($authorCollection as $author) {
                $aid = $author->getAuthorId();
                $authors[$aid] = $author->getData();
            }

            // get categories
            $categoryCollection = $this->categoryRepository->getActiveCategories();
            $categories = [];
            foreach ($categoryCollection as $category) {
                $cid = $category->getCategoryId();
                $categories[$cid] = $category->getData();
            }

            // get tags
            $tagCollection = $this->tagRepository->getActiveTags();
            $tags = [];
            foreach ($tagCollection as $tag) {
                $tid = $tag->getTagId();
                $tags[$tid] = $tag->getData();
            }

            // get post
            if (isset($args['id'])) {
                $args['id'] = (int)$args['id'];
                $post = $this->postRepository->getById($args['id']);
            } else {
                $post = $this->postRepository->getByUrlKey($args['urlKey']);
            }

            // convert data
            if(is_array($post->getData('categories'))) {
                $post->setData('categories', implode(', ', $post->getData('categories') ?: []));
            }
            if(is_array($post->getData('tag_ids'))) {
                $post->setData('tag_ids', implode(', ', $post->getData('tag_ids') ?: []));
            }
            $post->setViews($this->viewRepository->getViewCountByPostId($post->getPostId()));
            $post->setData('comment_count', $this->commentRepository->getCommentsInPost($post->getPostId())->addActiveFilter()->getSize());

            // get post data
            $result = $post->getData();

            // assign categories
            $result['mx_categories'] = [];
            $acats = is_array($post->getData('categories')) ? $post->getData('categories') : explode(",",$post->getData('categories'));
            foreach($acats as $temp) {
                if (is_numeric($temp)) $result['mx_categories'][] = $categories[trim($temp)];
            }

            // assign tags
            $result['mx_tags'] = [];
            $atags = is_array($post->getData('tag_ids')) ? $post->getData('tag_ids') : explode(",",$post->getData('tag_ids'));
            foreach($atags as $temp) {
                if (is_numeric($temp)) $result['mx_tags'][] = $tags[trim($temp)];
            }

            // assign author
            $result['mx_author'] = [];
            if (isset($authors[$result['author_id']])) { // is any author assigned?
                $result['mx_author'] = $authors[$result['author_id']];
            }

            // filter full content
            if (isset($result['full_content'])) {
                $result['full_content'] = $this->filterProvider->getPageFilter()->filter($result['full_content']);
            }

            // get related products
            if (isset($args['id'])) {
                $items = [];
                foreach ($this->getPostRelatedProducts->execute((int)$args['id']) as $product) {
                    $productData = $product->getData();
                    $productData['model'] = $product;
                    $productData['is_salable'] = (bool) $product->getIsSalable();
                    $productData['rating_summary'] = (int) $this->getRating($product);
                    $productData['reviews_count'] = (int) $product->getRatingSummary()->getReviewsCount();
                    $items[$product->getId()] = $productData;
                }
                $result['mx_related_products'] = ['items' => $items];
            }

            // get related posts only once
            if (isset($result['related_post_ids']) && $result['related_post_ids'] != "") {
                $items = [];
                foreach (explode(',',$result['related_post_ids']) as $relpostid) {
                    if ($relpostid != "") {
                        $item = $this->getPost(['id' => $relpostid]);
                    } else {
                        $item = [];
                    }
                    $items[$relpostid] = $item;
                }
                $result['mx_related_posts'] = ['items' => $items];
            } else {
                $result['mx_related_posts'] = ['items' => []];
            }
        }

        return $result;
    }

    /**
     * get posts by category
     *
     * @param integer $categoryId
     * @return object
     */
    public function getPostsByCategoryId(int $categoryId)
    {
        return $this->postRepository->getActivePosts()
            ->setUrlKeyIsNotNull()
            ->setDateOrder()
            ->addCategoryFilter($categoryId);
    }

    /**
     * get posts by tag
     *
     * @param integer $tagId
     * @return object
     */
    public function getPostsByTagId(int $tagId)
    {
        return $this->postRepository->getActivePosts()
            ->setUrlKeyIsNotNull()
            ->setDateOrder()
            ->addTagFilter($tagId);
    }

    /**
     * get posts by author
     *
     * @param integer $authorId
     * @return object
     */
    public function getPostsByAuthorId(int $authorId)
    {
        return $this->postRepository->getActivePosts()
            ->setUrlKeyIsNotNull()
            ->setDateOrder()
            ->addFieldToFilter(AuthorInterface::AUTHOR_ID, $authorId);
    }

    /**
     * get rating for product
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getRating(ProductInterface $product): string
    {
        $this->reviewFactory->create()->getEntitySummary($product);

        return $product->getRatingSummary() instanceof \Magento\Framework\DataObject
            ? (string)$product->getRatingSummary()->getRatingSummary()
            : (string)$product->getRatingSummary();
    }
}
