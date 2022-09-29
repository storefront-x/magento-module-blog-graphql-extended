<?php
declare(strict_types=1);

namespace StorefrontX\BlogGraphQlExtended\Model\Resolver;

use Magento\Framework\App\ResourceConnection;
use StorefrontX\BlogGraphQlExtended\Model\Post as PostProvider;
use Magento\Cms\Model\Template\FilterProvider;

class Post
{
    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var PostProvider
     */
    protected $postProvider;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    public function __construct(
        ResourceConnection $connection,
        PostProvider $postProvider,
        FilterProvider $filterProvider
    ) {
        $this->connection = $connection;
        $this->postProvider = $postProvider;
        $this->filterProvider = $filterProvider;
    }

    /**
     * afterResolve
     * @SuppressWarnings("unused")
     * @param Amasty\BlogGraphQl\Model\Resolver\Post $subject
     * @param array $result
     * @return array
     */
    public function afterResolve($subject, array $result):array
    {
        if(isset($result['items'])) {
            foreach($result['items'] as $key => $item) {
                $result['items'][$key] = $this->getMore($item);
            }
        } else {
            $result = $this->getMore($result);
        }

        return $result;
    }

    /**
     * get more data for each post
     *
     * @param array $result
     * @return array
     */
    public function getMore($result):array {
        $data = $this->postProvider->getPost(array('id' => $result['post_id']));
        $result['mx_categories'] = $data['mx_categories'];
        $result['mx_tags'] = $data['mx_tags'];
        $result['mx_author'] = $data['mx_author'];
        $result['mx_related_products'] = $data['mx_related_products'];
        $result['mx_related_posts'] = $data['mx_related_posts'];

        if (isset($result['full_content'])) {
            $result['full_content'] = $this->filterProvider->getPageFilter()->filter($result['full_content']);
        }

        return $result;
    }

}
