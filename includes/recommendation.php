<?php
/**
 * Advanced Content-Based Recommendation System with Cosine Similarity
 * - Non-logged users & users without purchases: 4+ star books
 * - Users with purchases: Cosine similarity based on book descriptions
 */

function getUserPurchaseHistory($userId) {
    global $conn;
    
    $history = [
        'authors' => [],
        'genres' => [],
        'purchased_books' => [], // Full book data for similarity
        'purchased_book_ids' => [],
        'has_purchases' => false
    ];
    
    $query = "SELECT DISTINCT b.id, b.author, b.genre, b.title, b.description, b.price
              FROM books b
              JOIN order_items oi ON b.id = oi.book_id
              JOIN orders o ON oi.order_id = o.id
              WHERE o.user_id = ? 
              AND o.payment_status = 'completed'
              ORDER BY o.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        error_log("Error getting purchase history for user $userId");
        return $history;
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        $history['purchased_book_ids'][] = $row['id'];
        $history['purchased_books'][] = $row; // Store full book data
        $history['has_purchases'] = true;
        
        // Count author purchases
        $author = trim($row['author']);
        if (!empty($author)) {
            $authorKey = strtolower($author);
            if (!isset($history['authors'][$authorKey])) {
                $history['authors'][$authorKey] = [
                    'name' => $author,
                    'count' => 0
                ];
            }
            $history['authors'][$authorKey]['count']++;
        }
        
        // Count genre purchases  
        $genre = trim($row['genre']);
        if (!empty($genre)) {
            $genreKey = strtolower($genre);
            if (!isset($history['genres'][$genreKey])) {
                $history['genres'][$genreKey] = [
                    'name' => $genre,
                    'count' => 0
                ];
            }
            $history['genres'][$genreKey]['count']++;
        }
    }
    
    return $history;
}

/**
 * Get 4+ star books for non-logged users and fallback
 */
function getHighlyRatedBooks($limit = 4, $excludeIds = []) {
    global $conn;
    
    $excludeClause = '';
    if (!empty($excludeIds)) {
        $excludeList = implode(',', array_map('intval', $excludeIds));
        $excludeClause = "AND b.id NOT IN ($excludeList)";
    }
    
    $query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
              FROM books b
              JOIN ratings r ON b.id = r.book_id
              WHERE b.is_available = 1
              AND b.quantity > 0
              $excludeClause
              GROUP BY b.id
              HAVING AVG(r.rating) >= 4.0
              ORDER BY AVG(r.rating) DESC, COUNT(r.rating) DESC
              LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $books = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
        }
    }
    
    return $books;
}

/**
 * Cosine Similarity Function
 */
function cosineSimilarity($vec1, $vec2) {
    $dotProduct = 0;
    $normA = 0;
    $normB = 0;

    foreach ($vec1 as $key => $val) {
        $dotProduct += $val * ($vec2[$key] ?? 0);
        $normA += $val * $val;
    }

    foreach ($vec2 as $val) {
        $normB += $val * $val;
    }

    return $normA && $normB ? $dotProduct / (sqrt($normA) * sqrt($normB)) : 0;
}

/**
 * Convert Text to Vector (Bag of Words)
 */
function textToVector($text, $vocab) {
    // Clean and tokenize text
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text); // Remove punctuation
    $words = array_filter(explode(' ', $text)); // Remove empty strings
    $words = array_count_values($words);
    
    $vector = [];
    foreach ($vocab as $word) {
        $vector[] = $words[$word] ?? 0;
    }

    return $vector;
}

/**
 * Build comprehensive text content for similarity
 */
function buildBookContent($book) {
    $content = '';
    
    // Add title (higher weight by repeating 3 times)
    $title = trim($book['title'] ?? '');
    if (!empty($title)) {
        $content .= str_repeat($title . ' ', 3);
    }
    
    // Add description (medium weight by repeating 2 times)
    $description = trim($book['description'] ?? '');
    if (!empty($description)) {
        $content .= str_repeat($description . ' ', 2);
    }
    
    // Add author (single weight)
    $author = trim($book['author'] ?? '');
    if (!empty($author)) {
        $content .= $author . ' ';
    }
    
    // Add genre (single weight)
    $genre = trim($book['genre'] ?? '');
    if (!empty($genre)) {
        $content .= $genre . ' ';
    }
    
    return trim($content);
}

/**
 * Get all available books for similarity comparison
 */
function getAllAvailableBooks($excludeIds = []) {
    global $conn;
    
    $excludeClause = '';
    if (!empty($excludeIds)) {
        $excludeList = implode(',', array_map('intval', $excludeIds));
        $excludeClause = "AND b.id NOT IN ($excludeList)";
    }
    
    $query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
              FROM books b
              LEFT JOIN ratings r ON b.id = r.book_id
              WHERE b.is_available = 1
              AND b.quantity > 0
              $excludeClause
              GROUP BY b.id
              ORDER BY b.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $books = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
        }
    }
    
    return $books;
}

/**
 * Content-Based Recommendations using Cosine Similarity
 */
function getContentBasedRecommendations($userId, $limit = 4) {
    global $conn;
    
    // Get user purchase history
    $userHistory = getUserPurchaseHistory($userId);
    
    // If no purchases, return highly rated books
    if (!$userHistory['has_purchases']) {
        error_log("User $userId: No purchase history - returning highly rated books");
        return getHighlyRatedBooks($limit);
    }
    
    $excludeIds = $userHistory['purchased_book_ids'];
    $purchasedBooks = $userHistory['purchased_books'];
    
    // Get all available books for comparison
    $availableBooks = getAllAvailableBooks($excludeIds);
    
    if (empty($availableBooks)) {
        error_log("User $userId: No available books found - returning highly rated");
        return getHighlyRatedBooks($limit, $excludeIds);
    }
    
    // Build vocabulary from all books (purchased + available)
    $allBooks = array_merge($purchasedBooks, $availableBooks);
    $vocab = [];
    
    foreach ($allBooks as $book) {
        $content = buildBookContent($book);
        $words = explode(' ', strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $content)));
        $words = array_filter($words); // Remove empty strings
        $vocab = array_unique(array_merge($vocab, $words));
    }
    
    // Remove common stop words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'book', 'books'];
    $vocab = array_diff($vocab, $stopWords);
    $vocab = array_values($vocab); // Re-index array
    
    error_log("User $userId: Built vocabulary with " . count($vocab) . " words");
    error_log("User $userId: Comparing against " . count($availableBooks) . " available books");
    
    // Convert purchased books to vectors
    $purchasedVectors = [];
    foreach ($purchasedBooks as $book) {
        $content = buildBookContent($book);
        $purchasedVectors[] = textToVector($content, $vocab);
    }
    
    // Calculate similarity scores for each available book
    $similarities = [];
    $authorMatches = []; // Books with author matches
    $otherBooks = [];    // Books without author matches
    
    foreach ($availableBooks as $index => $availableBook) {
        $availableContent = buildBookContent($availableBook);
        $availableVector = textToVector($availableContent, $vocab);
        
        $maxSimilarity = 0;
        $bestMatchTitle = '';
        $totalSimilarity = 0;
        
        // Compare with each purchased book
        foreach ($purchasedBooks as $pIndex => $purchasedBook) {
            $similarity = cosineSimilarity($purchasedVectors[$pIndex], $availableVector);
            $totalSimilarity += $similarity;
            
            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $bestMatchTitle = $purchasedBook['title'];
            }
        }
        
        $avgSimilarity = count($purchasedBooks) > 0 ? $totalSimilarity / count($purchasedBooks) : 0;
        
        // Final score: 60% best match + 40% average (same as reference system)
        $finalScore = ($maxSimilarity * 0.6) + ($avgSimilarity * 0.4);
        
        // Check for author match first
        $bookAuthor = strtolower(trim($availableBook['author']));
        $authorBonus = 0;
        $hasAuthorMatch = false;
        if (isset($userHistory['authors'][$bookAuthor])) {
            $authorBonus = min(3, $userHistory['authors'][$bookAuthor]['count']) * 5.0;
            $finalScore += $authorBonus;
            $hasAuthorMatch = true;
        }
        
        $availableBook['similarity_score'] = $finalScore;
        $availableBook['max_similarity'] = $maxSimilarity;
        $availableBook['avg_similarity'] = $avgSimilarity;
        $availableBook['author_bonus'] = $authorBonus;
        $availableBook['genre_bonus'] = 0; // Will be set later if needed
        $availableBook['best_match'] = $bestMatchTitle;
        $availableBook['recommendation_type'] = 'content_similarity';
        
        // Separate books with author matches from others
        if ($hasAuthorMatch) {
            $authorMatches[] = $availableBook;
        } else {
            $otherBooks[] = $availableBook;
        }
    }
    
    // ENHANCED: Process recommendations based on latest purchase priority
    $finalRecommendations = [];
    $usedBookIds = [];
    
    error_log("User $userId: Processing recommendations by purchase recency");
    
    // Process each purchased book in chronological order (latest first)
    foreach ($purchasedBooks as $purchaseIndex => $purchasedBook) {
        if (count($finalRecommendations) >= $limit) {
            break; // Already have enough recommendations
        }
        
        $purchasedAuthor = strtolower(trim($purchasedBook['author']));
        $purchasedGenre = strtolower(trim($purchasedBook['genre']));
        
        error_log("User $userId: Processing purchase #{$purchaseIndex}: '{$purchasedBook['title']}' by '{$purchasedBook['author']}' [{$purchasedBook['genre']}]");
        
        // Step 1: Look for author matches for this specific purchase
        $currentAuthorMatches = [];
        foreach ($authorMatches as $book) {
            $bookAuthor = strtolower(trim($book['author']));
            if ($bookAuthor === $purchasedAuthor && !in_array($book['id'], $usedBookIds)) {
                $book['purchase_priority'] = $purchaseIndex;
                $currentAuthorMatches[] = $book;
                $usedBookIds[] = $book['id'];
            }
        }
        
        // Sort by similarity and add to recommendations
        usort($currentAuthorMatches, function($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        foreach ($currentAuthorMatches as $book) {
            if (count($finalRecommendations) < $limit) {
                $finalRecommendations[] = $book;
                error_log("User $userId: Added author match: '{$book['title']}' for purchase '{$purchasedBook['title']}'");
            }
        }
        
        // Step 2: If no author matches found and still need books, look for genre matches
        if (empty($currentAuthorMatches) && count($finalRecommendations) < $limit) {
            $currentGenreMatches = [];
            foreach ($otherBooks as $book) {
                $bookGenre = strtolower(trim($book['genre']));
                if ($bookGenre === $purchasedGenre && !in_array($book['id'], $usedBookIds)) {
                    // Apply genre bonus
                    $genreBonus = min(3, $userHistory['genres'][$bookGenre]['count']) * 3.0;
                    $book['similarity_score'] += $genreBonus;
                    $book['genre_bonus'] = $genreBonus;
                    $book['purchase_priority'] = $purchaseIndex;
                    $currentGenreMatches[] = $book;
                    $usedBookIds[] = $book['id'];
                }
            }
            
            // Sort by similarity and add to recommendations
            usort($currentGenreMatches, function($a, $b) {
                return $b['similarity_score'] <=> $a['similarity_score'];
            });
            
            foreach ($currentGenreMatches as $book) {
                if (count($finalRecommendations) < $limit) {
                    $finalRecommendations[] = $book;
                    error_log("User $userId: Added genre match: '{$book['title']}' for purchase '{$purchasedBook['title']}'");
                }
            }
        }
    }
    
    // Step 3: If still need more books, add remaining relevant books
    if (count($finalRecommendations) < $limit) {
        $remainingBooks = [];
        
        // Add unused author matches
        foreach ($authorMatches as $book) {
            if (!in_array($book['id'], $usedBookIds)) {
                $book['purchase_priority'] = 999;
                $remainingBooks[] = $book;
            }
        }
        
        // Add unused books with genre bonus
        foreach ($otherBooks as $book) {
            if (!in_array($book['id'], $usedBookIds)) {
                $bookGenre = strtolower(trim($book['genre']));
                if (isset($userHistory['genres'][$bookGenre])) {
                    $genreBonus = min(3, $userHistory['genres'][$bookGenre]['count']) * 3.0;
                    $book['similarity_score'] += $genreBonus;
                    $book['genre_bonus'] = $genreBonus;
                    $book['purchase_priority'] = 999;
                    
                    $hasStrongRelevance = ($book['similarity_score'] > 0.3) || ($book['author_bonus'] > 0) || ($book['genre_bonus'] > 0);
                    if ($hasStrongRelevance) {
                        $remainingBooks[] = $book;
                    }
                }
            }
        }
        
        // Sort remaining books and add to fill up to limit
        usort($remainingBooks, function($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        foreach ($remainingBooks as $book) {
            if (count($finalRecommendations) < $limit) {
                $finalRecommendations[] = $book;
                error_log("User $userId: Added remaining book: '{$book['title']}'");
            }
        }
    }
    
    $recommendations = array_slice($finalRecommendations, 0, $limit);
    
    // FIXED: Default Recommender - Only use highly rated fallback if NO relevant books found
    if (count($recommendations) < $limit) {
        $hasRelevantMatches = false;
        foreach ($recommendations as $book) {
            if (($book['author_bonus'] ?? 0) > 0 || ($book['genre_bonus'] ?? 0) > 0) {
                $hasRelevantMatches = true;
                break;
            }
        }
        
        if (!$hasRelevantMatches) {
            $existingIds = array_column($recommendations, 'id');
            $remaining = $limit - count($recommendations);
            $highlyRated = getHighlyRatedBooks($remaining, array_merge($excludeIds, $existingIds));
            
            foreach ($highlyRated as $book) {
                if (count($recommendations) < $limit) {
                    $book['similarity_score'] = 1.0;
                    $book['recommendation_type'] = 'highly_rated_fallback';
                    $book['best_match'] = 'Highly rated book';
                    $book['author_bonus'] = 0;
                    $book['genre_bonus'] = 0;
                    $recommendations[] = $book;
                }
            }
            
            error_log("User $userId: Added " . count($highlyRated) . " highly rated books as fallback");
        }
    }
    
    // FINAL SAFETY: Ensure exactly 4 books maximum
    $recommendations = array_slice($recommendations, 0, $limit);
    
    // DETAILED LOGGING
    $purchasedTitles = array_column($purchasedBooks, 'title');
    $purchasedAuthors = array_keys($userHistory['authors']);
    $purchasedGenres = array_keys($userHistory['genres']);
    
    error_log("User $userId purchased: " . implode(', ', $purchasedTitles));
    error_log("User $userId preferred authors: " . implode(', ', $purchasedAuthors));
    error_log("User $userId preferred genres: " . implode(', ', $purchasedGenres));
    error_log("User $userId final recommendations count: " . count($recommendations));
    
    foreach ($recommendations as $index => $book) {
        $type = $book['recommendation_type'] ?? 'unknown';
        $score = round($book['similarity_score'] ?? 0, 3);
        $maxSim = round($book['max_similarity'] ?? 0, 3);
        $avgSim = round($book['avg_similarity'] ?? 0, 3);
        $authorBonus = round($book['author_bonus'] ?? 0, 1);
        $genreBonus = round($book['genre_bonus'] ?? 0, 1);
        $bestMatch = $book['best_match'] ?? 'None';
        $priority = $book['purchase_priority'] ?? 'N/A';
        
        error_log("Recommendation " . ($index + 1) . ": '{$book['title']}' by '{$book['author']}' [{$book['genre']}] (Type: $type, Final Score: $score, Priority: $priority, Author Bonus: $authorBonus, Genre Bonus: $genreBonus)");
    }
    
    return $recommendations;
}

/**
 * Main function to get recommendations
 */
function getRecommendedBooks($userId = null, $limit = 4) {
    if (!$userId) {
        // Non-logged users get highly rated books
        error_log("Non-logged user: Returning highly rated books");
        return getHighlyRatedBooks($limit);
    }
    
    return getContentBasedRecommendations($userId, $limit);
}

?>