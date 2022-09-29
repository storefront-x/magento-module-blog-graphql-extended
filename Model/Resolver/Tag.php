<?php
declare(strict_types=1);

namespace StorefrontX\BlogGraphQlExtended\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\App\ResourceConnection;
use StorefrontX\BlogGraphQlExtended\Model\Post;
use Amasty\Blog\Api\TagRepositoryInterface;

class Tag implements ResolverInterface
{
    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var TagRepositoryInterface
     */
    private $tagRepository;

    /**
     * @var Post
     */
    protected $postProvider;

    public function __construct(
        ResourceConnection $connection,
        TagRepositoryInterface $tagRepository,
        Post $postProvider
    ) {
        $this->connection = $connection;
        $this->tagRepository = $tagRepository;
        $this->postProvider = $postProvider;
    }

    /**
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|\Magento\Framework\GraphQl\Query\Resolver\Value|mixed
     * @throws \Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $result = $this->tagRepository->getById($args['id'])->getData();
        if (isset($args['id']) && is_numeric($args['id'])) {
            $posts = $this->postProvider->getPostsByTagId((int)$args['id']);
            $result['post_count'] = $posts->getSize();
            foreach ($posts as $post) {
                $result['mx_posts'][] = $this->postProvider->getPost(array('id'=>$post->getPostId()));
            }
        }
        return $result;
    }
}
