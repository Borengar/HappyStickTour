<?php

require_once 'Database.php';

class OsuApi {

  // get api key from https://osu.ppy.sh/p/api
  private $osu_api_key;

  // set to false if api should just use local copies
  // used if bancho is dead or slow
  private $use_api = true;

  function __construct() {
    $config = parse_ini_file('config.ini');
    $this->osu_api_key = $config['osuApiKey'];
  }

  public function getBeatmap($beatmap_id) {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
    $stmt = $db->prepare('SELECT approved, approved_date, last_update, artist, beatmap_id, beatmapset_id, bpm, creator, difficultyrating, diff_size, diff_overall, diff_approach, diff_drain, hit_length, source, genre_id, language_id, title, total_length, version, file_md5, mode, tags, favourite_count, playcount, passcount, max_combo
      FROM osu_beatmaps 
      WHERE beatmap_id = :beatmap_id');
    $stmt->bindValue(':beatmap_id', $beatmap_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows[0])) {
      $response = new StdClass;
      $response->approved = $rows[0]['approved'];
      $response->approved_date = $rows[0]['approved_date'];
      $response->last_update = $rows[0]['last_update'];
      $response->artist = $rows[0]['artist'];
      $response->beatmap_id = $rows[0]['beatmap_id'];
      $response->beatmapset_id = $rows[0]['beatmapset_id'];
      $response->bpm = $rows[0]['bpm'];
      $response->creator = $rows[0]['creator'];
      $response->difficultyrating = $rows[0]['difficultyrating'];
      $response->diff_size = $rows[0]['diff_size'];
      $response->diff_overall = $rows[0]['diff_overall'];
      $response->diff_approach = $rows[0]['diff_approach'];
      $response->diff_drain = $rows[0]['diff_drain'];
      $response->hit_length = $rows[0]['hit_length'];
      $response->source = $rows[0]['source'];
      $response->genre_id = $rows[0]['genre_id'];
      $response->language_id = $rows[0]['language_id'];
      $response->title = $rows[0]['title'];
      $response->total_length = $rows[0]['total_length'];
      $response->version = $rows[0]['version'];
      $response->file_md5 = $rows[0]['file_md5'];
      $response->mode = $rows[0]['mode'];
      $response->tags = $rows[0]['tags'];
      $response->favourite_count = $rows[0]['favourite_count'];
      $response->playcount = $rows[0]['playcount'];
      $response->passcount = $rows[0]['passcount'];
      $response->max_combo = $rows[0]['max_combo'];
    } else {
      $request_url = 'https://osu.ppy.sh/api/get_beatmaps?k=' . $this->osu_api_key . '&b=' . urlencode($beatmap_id);
      if ($this->use_api) {
        $response = json_decode(@file_get_contents($request_url, 0, stream_context_create(array('http' => array('timeout' => 5)))))[0];
        if (empty($response)) {
          $this->use_api = false;
          return null;
        }
        $stmt = $db->prepare('INSERT INTO osu_beatmaps (approved, approved_date, last_update, artist, beatmap_id, beatmapset_id, bpm, creator, difficultyrating, diff_size, diff_overall, diff_approach, diff_drain, hit_length, source, genre_id, language_id, title, total_length, version, file_md5, mode, tags, favourite_count, playcount, passcount, max_combo)
          VALUES (:approved, :approved_date, :last_update, :artist, :beatmap_id, :beatmapset_id, :bpm, :creator, :difficultyrating, :diff_size, :diff_overall, :diff_approach, :diff_drain, :hit_length, :source, :genre_id, :language_id, :title, :total_length, :version, :file_md5, :mode, :tags, :favourite_count, :playcount, :passcount, :max_combo)');
        $stmt->bindValue(':approved', $response->approved, PDO::PARAM_INT);
        $stmt->bindValue(':approved_date', (DateTime::createFromFormat('Y-m-d H:i:s', $response->approved_date) ?: new DateTime())->modify('-8 hours')->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':last_update', DateTime::createFromFormat('Y-m-d H:i:s', $response->last_update)->modify('-8 hours')->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':artist', $response->artist, PDO::PARAM_STR);
        $stmt->bindValue(':beatmap_id', $response->beatmap_id, PDO::PARAM_INT);
        $stmt->bindValue(':beatmapset_id', $response->beatmapset_id, PDO::PARAM_INT);
        $stmt->bindValue(':bpm', $response->bpm, PDO::PARAM_INT);
        $stmt->bindValue(':creator', $response->creator, PDO::PARAM_STR);
        $stmt->bindValue(':difficultyrating', $response->difficultyrating, PDO::PARAM_STR);
        $stmt->bindValue(':diff_size', $response->diff_size, PDO::PARAM_STR);
        $stmt->bindValue(':diff_overall', $response->diff_overall, PDO::PARAM_STR);
        $stmt->bindValue(':diff_approach', $response->diff_approach, PDO::PARAM_STR);
        $stmt->bindValue(':diff_drain', $response->diff_drain, PDO::PARAM_STR);
        $stmt->bindValue(':hit_length', $response->hit_length, PDO::PARAM_INT);
        $stmt->bindValue(':source', $response->source, PDO::PARAM_STR);
        $stmt->bindValue(':genre_id', $response->genre_id, PDO::PARAM_INT);
        $stmt->bindValue(':language_id', $response->language_id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $response->title, PDO::PARAM_STR);
        $stmt->bindValue(':total_length', $response->total_length, PDO::PARAM_INT);
        $stmt->bindValue(':version', $response->version, PDO::PARAM_STR);
        $stmt->bindValue(':file_md5', $response->file_md5, PDO::PARAM_STR);
        $stmt->bindValue(':mode', $response->mode, PDO::PARAM_INT);
        $stmt->bindValue(':tags', $response->tags, PDO::PARAM_STR);
        $stmt->bindValue(':favourite_count', $response->favourite_count, PDO::PARAM_INT);
        $stmt->bindValue(':playcount', $response->playcount, PDO::PARAM_INT);
        $stmt->bindValue(':passcount', $response->passcount, PDO::PARAM_INT);
        $stmt->bindValue(':max_combo', $response->max_combo, PDO::PARAM_INT);
        $stmt->execute();
      } else {
        $response = null;
      }
    }
    return $response;
  }

  public function getUser($username) {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare('SELECT user_id, username, count300, count100, count50, playcount, ranked_score, total_score, pp_rank, level, pp_raw, accuracy, count_rank_ss, count_rank_s, count_rank_a, country, pp_country_rank, cache_update
      FROM osu_users
      WHERE user_id = :user_id OR username = :username');
    $stmt->bindValue(':user_id', $username, PDO::PARAM_STR);
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows[0])) {
      $cacheTime = new DateTime($rows[0]['cache_update']);
      $timeDifference = $cacheTime->diff(new DateTime());
      if ($timeDifference->d >= 1 && $this->use_api) {
        $request_url = 'https://osu.ppy.sh/api/get_user?k=' . $this->osu_api_key . '&u=' . urlencode($username);
        $response = json_decode(@file_get_contents($request_url, 0, stream_context_create(array('http' => array('timeout' => 5)))))[0];
        if (empty($response)) {
          $this->use_api = false;
          return null;
        }
        $response->level = round($response->level, 0);
        $response->accuracy = round($response->accuracy, 2);
        $stmt = $db->prepare('UPDATE osu_users
          SET username = :username, count300 = :count300, count100 = :count100, count50 = :count50, playcount = :playcount, ranked_score = :ranked_score, total_score = :total_score, pp_rank = :pp_rank, level = :level, pp_raw = :pp_raw, accuracy = :accuracy, count_rank_ss = :count_rank_ss, count_rank_s = :count_rank_s, count_rank_a = :count_rank_a, country = :country, pp_country_rank = :pp_country_rank, cache_update = NOW()
          WHERE user_id = :user_id');
        $stmt->bindValue(':username', $response->username, PDO::PARAM_STR);
        $stmt->bindValue(':count300', $response->count300, PDO::PARAM_INT);
        $stmt->bindValue(':count100', $response->count100, PDO::PARAM_INT);
        $stmt->bindValue(':count50', $response->count50, PDO::PARAM_INT);
        $stmt->bindValue(':playcount', $response->playcount, PDO::PARAM_INT);
        $stmt->bindValue(':ranked_score', $response->ranked_score, PDO::PARAM_INT);
        $stmt->bindValue(':total_score', $response->total_score, PDO::PARAM_INT);
        $stmt->bindValue(':pp_rank', $response->pp_rank, PDO::PARAM_INT);
        $stmt->bindValue(':level', $response->level, PDO::PARAM_INT);
        $stmt->bindValue(':pp_raw', $response->pp_raw, PDO::PARAM_STR);
        $stmt->bindValue(':accuracy', $response->accuracy, PDO::PARAM_STR);
        $stmt->bindValue(':count_rank_ss', $response->count_rank_ss, PDO::PARAM_INT);
        $stmt->bindValue(':count_rank_s', $response->count_rank_s, PDO::PARAM_INT);
        $stmt->bindValue(':count_rank_a', $response->count_rank_a, PDO::PARAM_INT);
        $stmt->bindValue(':country', $response->country, PDO::PARAM_STR);
        $stmt->bindValue(':pp_country_rank', $response->pp_country_rank, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $response->user_id, PDO::PARAM_INT);
        $stmt->execute();
      } else {
        $response = new StdClass;
        $response->user_id = $rows[0]['user_id'];
        $response->username = $rows[0]['username'];
        $response->count300 = $rows[0]['count300'];
        $response->count100 = $rows[0]['count100'];
        $response->count50 = $rows[0]['count50'];
        $response->playcount = $rows[0]['playcount'];
        $response->ranked_score = $rows[0]['ranked_score'];
        $response->total_score = $rows[0]['total_score'];
        $response->pp_rank = $rows[0]['pp_rank'];
        $response->level = $rows[0]['level'];
        $response->pp_raw = $rows[0]['pp_raw'];
        $response->accuracy = $rows[0]['accuracy'];
        $response->count_rank_ss = $rows[0]['count_rank_ss'];
        $response->count_rank_s = $rows[0]['count_rank_s'];
        $response->count_rank_a = $rows[0]['count_rank_a'];
        $response->country = $rows[0]['country'];
        $response->pp_country_rank = $rows[0]['pp_country_rank'];
      }
    } else {
      if ($this->use_api) {
        $request_url = 'https://osu.ppy.sh/api/get_user?k=' . $this->osu_api_key . '&u=' . urlencode($username);
        $response = json_decode(@file_get_contents($request_url, 0, stream_context_create(array('http' => array('timeout' => 5)))))[0];
        if (empty($response)) {
          $this->use_api = false;
          return null;
        }
        $response->level = round($response->level, 0);
        $response->accuracy = round($response->accuracy, 2);
        $stmt = $db->prepare('INSERT INTO osu_users (user_id, username, count300, count100, count50, playcount, ranked_score, total_score, pp_rank, level, pp_raw, accuracy, count_rank_ss, count_rank_s, count_rank_a, country, pp_country_rank, cache_update)
          VALUES (:user_id, :username, :count300, :count100, :count50, :playcount, :ranked_score, :total_score, :pp_rank, :level, :pp_raw, :accuracy, :count_rank_ss, :count_rank_s, :count_rank_a, :country, :pp_country_rank, NOW())');
        $stmt->bindValue(':user_id', $response->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':username', $response->username, PDO::PARAM_STR);
        $stmt->bindValue(':count300', $response->count300, PDO::PARAM_INT);
        $stmt->bindValue(':count100', $response->count100, PDO::PARAM_INT);
        $stmt->bindValue(':count50', $response->count50, PDO::PARAM_INT);
        $stmt->bindValue(':playcount', $response->playcount, PDO::PARAM_INT);
        $stmt->bindValue(':ranked_score', $response->ranked_score, PDO::PARAM_INT);
        $stmt->bindValue(':total_score', $response->total_score, PDO::PARAM_INT);
        $stmt->bindValue(':pp_rank', $response->pp_rank, PDO::PARAM_INT);
        $stmt->bindValue(':level', $response->level, PDO::PARAM_INT);
        $stmt->bindValue(':pp_raw', $response->pp_raw, PDO::PARAM_STR);
        $stmt->bindValue(':accuracy', $response->accuracy, PDO::PARAM_STR);
        $stmt->bindValue(':count_rank_ss', $response->count_rank_ss, PDO::PARAM_INT);
        $stmt->bindValue(':count_rank_s', $response->count_rank_s, PDO::PARAM_INT);
        $stmt->bindValue(':count_rank_a', $response->count_rank_a, PDO::PARAM_INT);
        $stmt->bindValue(':country', $response->country, PDO::PARAM_STR);
        $stmt->bindValue(':pp_country_rank', $response->pp_country_rank, PDO::PARAM_INT);
        $stmt->execute();
      } else {
        $response = null;
      }
    }
    return $response;
  }

  public function getMatch($match_id) {
    $database = new Database();
    $db = $database->getConnection();
    if ($this->use_api) {
      $request_url = 'https://osu.ppy.sh/api/get_match?k=' . $this->osu_api_key . '&mp=' . urlencode($match_id);
      $response = json_decode(@file_get_contents($request_url, 0, stream_context_create(array('http' => array('timeout' => 5)))));
      if (!empty($response)) {
        $stmt = $db->prepare('SELECT COUNT(*) as rowcount
          FROM osu_matches
          WHERE match_id = :match_id');
        $stmt->bindValue(':match_id', $response->match->match_id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (isset($rows[0]) && isset($rows[0]['rowcount'])) {
          if ($rows[0]['rowcount'] == '0') {
            $stmt = $db->prepare('INSERT INTO osu_matches (match_id, name, start_time)
              VALUES (:match_id, :name, :start_time)');
            $stmt->bindValue(':match_id', $response->match->match_id, PDO::PARAM_INT);
            $stmt->bindValue(':name', $response->match->name, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', DateTime::createFromFormat('Y-m-d H:i:s', $response->match->start_time)->modify('-8 hours')->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
          }
        }
        foreach ($response->games as $game) {
          if (!empty($game->end_time)) {
            $stmt = $db->prepare('SELECT COUNT(*) as rowcount
              FROM osu_games
              WHERE game_id = :game_id');
            $stmt->bindValue(':game_id', $game->game_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (isset($rows[0]) && isset($rows[0]['rowcount'])) {
              if ($rows[0]['rowcount'] == '0') {
                $stmt = $db->prepare('SELECT mappool_slots.beatmap_id
                  FROM mappool_slots INNER JOIN mappools ON mappool_slots.mappool = mappools.id INNER JOIN lobbies ON mappools.round = lobbies.round
                  WHERE lobbies.match_id = :match_id');
                $stmt->bindValue(':match_id', $response->match->match_id, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $counts = false;
                foreach ($rows as $beatmap) {
                  if ($beatmap['beatmap_id'] == $game->beatmap_id) {
                    $counts = true;
                    break;
                  }
                }

                $stmt = $db->prepare('INSERT INTO osu_games (match_id, game_id, start_time, end_time, beatmap_id, play_mode, match_type, scoring_type, team_type, mods, counts)
                  VALUES (:match_id, :game_id, :start_time, :end_time, :beatmap_id, :play_mode, :match_type, :scoring_type, :team_type, :mods, :counts)');
                $stmt->bindValue(':match_id', $response->match->match_id, PDO::PARAM_INT);
                $stmt->bindValue(':game_id', $game->game_id, PDO::PARAM_INT);
                $stmt->bindValue(':start_time', DateTime::createFromFormat('Y-m-d H:i:s', $game->start_time)->modify('-8 hours')->format('Y-m-d H:i:s'), PDO::PARAM_STR);
                $stmt->bindValue(':end_time', DateTime::createFromFormat('Y-m-d H:i:s', $game->end_time)->modify('-8 hours')->format('Y-m-d H:i:s'), PDO::PARAM_STR);
                $stmt->bindValue(':beatmap_id', $game->beatmap_id, PDO::PARAM_INT);
                $stmt->bindValue(':play_mode', $game->play_mode, PDO::PARAM_INT);
                $stmt->bindValue(':match_type', $game->match_type, PDO::PARAM_INT);
                $stmt->bindValue(':scoring_type', $game->scoring_type, PDO::PARAM_INT);
                $stmt->bindValue(':team_type', $game->team_type, PDO::PARAM_INT);
                $stmt->bindValue(':mods', $game->mods, PDO::PARAM_INT);
                $stmt->bindValue(':counts', $counts, PDO::PARAM_BOOL);
                $stmt->execute();

                foreach ($game->scores as $score) {
                  $stmt = $db->prepare('INSERT INTO osu_scores (game_id, slot, team, user_id, score, maxcombo, rank, count50, count100, count300, countmiss, countgeki, countkatu, perfect, pass)
                    VALUES (:game_id, :slot, :team, :user_id, :score, :maxcombo, :rank, :count50, :count100, :count300, :countmiss, :countgeki, :countkatu, :perfect, :pass)');
                  $stmt->bindValue(':game_id', $game->game_id, PDO::PARAM_INT);
                  $stmt->bindValue(':slot', $score->slot, PDO::PARAM_INT);
                  $stmt->bindValue(':team', $score->team, PDO::PARAM_INT);
                  $stmt->bindValue(':user_id', $score->user_id, PDO::PARAM_INT);
                  $stmt->bindValue(':score', $score->score, PDO::PARAM_INT);
                  $stmt->bindValue(':maxcombo', $score->maxcombo, PDO::PARAM_INT);
                  $stmt->bindValue(':rank', $score->rank, PDO::PARAM_INT);
                  $stmt->bindValue(':count50', $score->count50, PDO::PARAM_INT);
                  $stmt->bindValue(':count100', $score->count100, PDO::PARAM_INT);
                  $stmt->bindValue(':count300', $score->count300, PDO::PARAM_INT);
                  $stmt->bindValue(':countmiss', $score->countmiss, PDO::PARAM_INT);
                  $stmt->bindValue(':countgeki', $score->countgeki, PDO::PARAM_INT);
                  $stmt->bindValue(':countkatu', $score->countkatu, PDO::PARAM_INT);
                  $stmt->bindValue(':perfect', $score->perfect, PDO::PARAM_INT);
                  $stmt->bindValue(':pass', $score->pass, PDO::PARAM_INT);
                  $stmt->execute();
                }
              }
            }
          }
        }
      }
    }

    $response = new StdClass;
    $response->match = new StdClass;
    $response->match->match_id = $match_id;
    $response->games = array();
    $stmt = $db->prepare('SELECT name, start_time
      FROm osu_matches
      WHERE match_id = :match_id');
    $stmt->bindValue(':match_id', $match_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (isset($rows[0])) {
      $response->match->name = $rows[0]['name'];
      $response->match->start_time = $rows[0]['start_time'];
      $stmt = $db->prepare('SELECT game_id, start_time, end_time, beatmap_id, play_mode, match_type, scoring_type, team_type, mods, counts, picked_by
        FROM osu_games
        WHERE match_id = :match_id');
      $stmt->bindValue(':match_id', $match_id, PDO::PARAM_INT);
      $stmt->execute();
      $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($games as $game) {
        $gameObject = new StdClass;
        $gameObject->beatmap = $this->getBeatmap($game['beatmap_id']);
        $gameObject->game_id = $game['game_id'];
        $gameObject->start_time = $game['start_time'];
        $gameObject->end_time = $game['end_time'];
        $gameObject->beatmap_id = $game['beatmap_id'];
        $gameObject->play_mode = $game['play_mode'];
        $gameObject->match_type = $game['match_type'];
        $gameObject->scoring_type = $game['scoring_type'];
        $gameObject->team_type = $game['team_type'];
        $gameObject->mods = $game['mods'];
        $gameObject->counts = $game['counts'];
        $gameObject->picked_by = $game['picked_by'];
        $gameObject->scores = array();
        $stmt = $db->prepare('SELECT score_id, slot, team, user_id, score, maxcombo, rank, count50, count100, count300, countmiss, countgeki, countkatu, perfect, pass
          FROM osu_scores
          WHERE game_id = :game_id');
        $stmt->bindValue(':game_id', $gameObject->game_id, PDO::PARAM_INT);
        $stmt->execute();
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scores as $score) {
          $scoreObject = new StdClass;
          $scoreObject->osu_profile = $this->getUser($score['user_id']);
          $scoreObject->slot = $score['slot'];
          $scoreObject->team = $score['team'];
          $scoreObject->user_id = $score['user_id'];
          $scoreObject->score = $score['score'];
          $scoreObject->maxcombo = $score['maxcombo'];
          $scoreObject->rank = $score['rank'];
          $scoreObject->count50 = $score['count50'];
          $scoreObject->count100 = $score['count100'];
          $scoreObject->count300 = $score['count300'];
          $scoreObject->countmiss = $score['countmiss'];
          $scoreObject->countgeki = $score['countgeki'];
          $scoreObject->countkatu = $score['countkatu'];
          $scoreObject->perfect = $score['perfect'];
          $scoreObject->pass = $score['pass'];
          $gameObject->scores[] = $scoreObject;
        }
        usort($gameObject->scores, function($a, $b) {
          if ($a->pass == 0 && $b->pass == 0) {
            return $b->score - $a->score;
          }
          if ($a->pass == 0) {
            return 1;
          }
          if ($b->pass == 0) {
            return -1;
          }
          return $b->score - $a->score;
        });
        $response->games[] = $gameObject;
      }
    }

    return $response;
  }

}

?>