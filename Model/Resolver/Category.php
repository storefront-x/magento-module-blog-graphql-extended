<?php
declare(strict_types=1);

namespace StorefrontX\BlogGraphQlExtended\Model\Resolver;

use Magento\Framework\App\ResourceConnection;
use StorefrontX\BlogGraphQlExtended\Model\Post;

class Category
{
    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var Post
     */
    protected $postProvider;

    public function __construct(
        ResourceConnection $connection,
        Post $postProvider
    ) {
        $this->connection = $connection;
        $this->postProvider = $postProvider;
    }

    /**
     * afterResolve
     * @SuppressWarnings("unused")
     * @param Amasty\BlogGraphQl\Model\Resolver\Category $subject
     * @param array $result
     * @return array
     */
    public function afterResolve($subject, array $result):array
    {
        if (isset($result['category_id']) && is_numeric($result['category_id'])) {
            $posts = $this->postProvider->getPostsByCategoryId((int)$result['category_id']);
            foreach ($posts as $post) {
                $result['mx_posts'][] = $this->postProvider->getPost(array('id'=>$post->getPostId()));
            }
        }
        return $result;
    }
}
