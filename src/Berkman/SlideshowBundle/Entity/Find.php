<?php

namespace Berkman\SlideshowBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Berkman\SlideshowBundle\Entity\Find
 */
class Find
{

	const RESULTS_PER_PAGE = 25;

    /**
     * @var string $keyword
     */
    private $keyword;

    /**
     * @var array $repos
     */
    private $repos;

	/**
	 * @var array An array that keeps track of various repo positions
	 */
	private $repoPositions;

    /**
     * @var array $images
     */
    private $images;

	/**
	 * @var int $currentPage
	 */
	private $currentPage;

	/**
	 * @var int $numResults
	 */
	private $totalResults;

    /**
     * Set keyword
     *
     * @param string $keyword
     */
    public function setKeyword($keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * Get keyword
     *
     * @return string $keyword
     */
    public function getKeyword()
    {
        return $this->keyword;
    }

    /**
     * Get total number of results
     *
     * @return int $numResults
     */
    public function getTotalResults()
    {
        return $this->totalResults;
    }

    /**
     * Set repos
     *
     * @param array $repos
     */
    public function setRepos($repos)
    {
        $this->repos = $repos;
    }

    /**
     * Get repos
     *
     * @return array $repos
     */
    public function getRepos()
    {
        return $this->repos;
    }

    /**
     * Get images given a keyword and page
     *
     * @return array $results
     */
	public function getImages($keyword = null, $page = null)
	{
		if (($keyword == null || $keyword == $this->keyword) && $page == $this->currentPage) {
			return $this->images;
		}
		if (empty($keyword) && !empty($this->keyword)) {
			$keyword = $this->keyword;
		}
		elseif (empty($this->keyword) && !empty($keyword)) {
			$this->keyword = $keyword;
		}
		else {
			#throw exception
		}
		$images = array();
		$totalResults = 0;
		$resultsPerRepo = floor(self::RESULTS_PER_PAGE / count($this->repos));
		$reposFirstIndex = $page * $resultsPerRepo - $resultsPerRepo;
		$reposLastIndex = $reposFirstIndex + $resultsPerRepo - 1;
		$lastRepoLastIndex = $reposLastIndex + self::RESULTS_PER_PAGE % ($resultsPerRepo * count($this->repos));

		foreach ($this->repos as $repo) {
			if ($repo == end($this->repos)) {
				$searchResults = $repo->getSearchResults($keyword, $reposFirstIndex, $lastRepoLastIndex);
			}
			else {
				$searchResults = $repo->getSearchResults($keyword, $reposFirstIndex, $reposLastIndex);
			}
			$images += $searchResults['images'];
			$totalResults += $searchResults['totalResults'];
		}

		$this->totalResults = $totalResults;
		$this->images = $images;

		return $images;
	}

	public function __construct($repos = null)
	{
		if ($repos) {
			$this->repos = $repos;
		}
		$this->currentPage = 1;
	}
}

