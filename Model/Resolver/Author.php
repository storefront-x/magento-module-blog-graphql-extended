<?php
declare(strict_types=1);

namespace StorefrontX\BlogGraphQlExtended\Model\Resolver;

use Magento\Framework\App\ResourceConnection;
use StorefrontX\BlogGraphQlExtended\Model\Post;

class Author
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
     * @param Amasty\BlogGraphQl\Model\Resolver\Author $subject
     * @param array $result
     * @return array
     */
    public function afterResolve($subject, array $result):array
    {
        if (isset($result['author_id']) && is_numeric($result['author_id'])) {
            $posts = $this->postProvider->getPostsByAuthorId((int)$result['author_id']);
            $result['post_count'] = $posts->getSize();
            foreach ($posts as $post) {
                $result['mx_posts'][] = $this->postProvider->getPost(array('id'=>$post->getPostId()));
            }
        }
        return $result;
    }
}
