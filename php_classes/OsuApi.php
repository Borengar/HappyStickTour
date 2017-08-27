<?php

require_once 'Database.php';

class OsuApi {
  public function getBeatmap($beatmapId) {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare('SELECT beatmap_id as beatmapId, beatmapset_id as beatmapsetId, title, artist, version, cover, preview_url as previewUrl, total_length as totalLength, bpm, count_circles as countCircles, count_sliders as countSliders, cs, drain, accuracy, ar, difficulty_rating as difficultyRating
      FROM osu_beatmaps
      WHERE beatmap_id = :beatmap_id');
    $stmt->bindValue(':beatmap_id', $beatmapId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
    $returnValue = new StdClass;
    if (!isset($rows[0])) {
      $curl = curl_init();
      curl_setopt_array($curl, array(
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_FOLLOWLOCATION => 1,
          CURLOPT_URL => 'https://osu.ppy.sh/beatmaps/' . $beatmapId
        )
      );
      $html = curl_exec($curl);
      curl_close($curl);
      $dom = new DOMDocument();
      @$dom->loadHTML($html);
      $beatmapset = json_decode($dom->getElementById('json-beatmapset')->textContent);
      foreach ($beatmapset->beatmaps as $value) {
        if ($value->id == $beatmapId) {
          $beatmap = $value;
          break;
        }
      }

      $returnValue->beatmapId = $beatmap->id;
      $returnValue->beatmapsetId = $beatmapset->id;
      $returnValue->title = $beatmapset->title;
      $returnValue->artist = $beatmapset->artist;
      $returnValue->version = $beatmap->version;
      $returnValue->cover = $beatmapset->covers->{'cover@2x'};
      $returnValue->previewUrl = $beatmapset->preview_url;
      $returnValue->totalLength = $beatmap->total_length;
      $returnValue->bpm = $beatmapset->bpm;
      $returnValue->countCircles = $beatmap->count_circles;
      $returnValue->countSliders = $beatmap->count_sliders;
      $returnValue->cs = $beatmap->cs;
      $returnValue->drain = $beatmap->drain;
      $returnValue->accuracy = $beatmap->accuracy;
      $returnValue->ar = $beatmap->ar;
      $returnValue->difficultyRating = $beatmap->difficulty_rating;

      $stmt = $db->prepare('INSERT INTO osu_beatmaps (beatmap_id, beatmapset_id, title, artist, version, cover, preview_url, total_length, bpm, count_circles, count_sliders, cs, drain, accuracy, ar, difficulty_rating)
        VALUES (:beatmap_id, :beatmapset_id, :title, :artist, :version, :cover, :preview_url, :total_length, :bpm, :count_circles, :count_sliders, :cs, :drain, :accuracy, :ar, :difficulty_rating)');
      $stmt->bindValue(':beatmap_id', $returnValue->beatmapId, PDO::PARAM_INT);
      $stmt->bindValue(':beatmapset_id', $returnValue->beatmapsetId, PDO::PARAM_INT);
      $stmt->bindValue(':title', $returnValue->title, PDO::PARAM_STR);
      $stmt->bindValue(':artist', $returnValue->artist, PDO::PARAM_STR);
      $stmt->bindValue(':version', $returnValue->version, PDO::PARAM_STR);
      $stmt->bindValue(':cover', $returnValue->cover, PDO::PARAM_STR);
      $stmt->bindValue(':preview_url', $returnValue->previewUrl, PDO::PARAM_STR);
      $stmt->bindValue(':total_length', $returnValue->totalLength, PDO::PARAM_INT);
      $stmt->bindValue(':bpm', $returnValue->bpm, PDO::PARAM_STR);
      $stmt->bindValue(':count_circles', $returnValue->countCircles, PDO::PARAM_INT);
      $stmt->bindValue(':count_sliders', $returnValue->countSliders, PDO::PARAM_INT);
      $stmt->bindValue(':cs', $returnValue->cs, PDO::PARAM_STR);
      $stmt->bindValue(':drain', $returnValue->drain, PDO::PARAM_STR);
      $stmt->bindValue(':accuracy', $returnValue->accuracy, PDO::PARAM_INT);
      $stmt->bindValue(':ar', $returnValue->ar, PDO::PARAM_STR);
      $stmt->bindValue(':difficulty_rating', $returnValue->difficultyRating, PDO::PARAM_STR);
      $stmt->execute();
    } else {
      $returnValue = $rows[0];
    }

    return $returnValue;
  }

  public function getUser($userId) {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare('SELECT id, username, avatar_url as avatarUrl, hit_accuracy as hitAccuracy, level, play_count as playCount, pp, rank, rank_history as rankHistory, best_score as bestScore, playstyle, join_date as joinDate, country, cache_update as cacheUpdate
      FROM osu_users
      WHERE id = :id OR username = :username');
    $stmt->bindValue(':id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':username', $userId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
    $returnValue = new StdClass;
    if (!isset($rows[0]) || (isset($rows[0]) && (new DateTime($rows[0]->cacheUpdate))->diff(new DateTime())->d >= 3)) {
      $curl = curl_init();
      curl_setopt_array($curl, array(
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_FOLLOWLOCATION => 1,
          CURLOPT_URL => 'https://osu.ppy.sh/users/' . $userId
        )
      );
      $html = curl_exec($curl);
      curl_close($curl);
      $dom = new DOMDocument();
      @$dom->loadHTML($html);
      $user = json_decode($dom->getElementById('json-user')->textContent);

      $returnValue->id = $user->id;
      $returnValue->username = $user->username;
      $returnValue->avatarUrl = $user->avatar_url;
      $returnValue->hitAccuracy = $user->allStatistics->osu->hit_accuracy;
      $returnValue->level = $user->allStatistics->osu->level->current;
      $returnValue->playCount = $user->allStatistics->osu->play_count;
      $returnValue->pp = $user->allStatistics->osu->pp;
      $returnValue->rank = $user->allStatistics->osu->rank->global;
      $returnValue->rankHistory = $user->allRankHistories->osu->data;
      $returnValue->bestScore = $user->allScoresBest->osu[0]->beatmapset->artist . ' - ' . $user->allScoresBest->osu[0]->beatmapset->title . ' [' . $user->allScoresBest->osu[0]->beatmap->version . ']';
      if (count($user->allScoresBest->osu[0]->mods) > 0) {
        $returnValue->bestScore .= ' +' . join(',', $user->allScoresBest->osu[0]->mods);
      }
      $returnValue->bestScore .= ' (' . $user->allScoresBest->osu[0]->pp . 'PP)';
      $returnValue->playstyle = join(' + ', $user->playstyle);
      $returnValue->joinDate = (new DateTime($user->join_date))->format('Y-m-d H:i:s');
      $returnValue->country = $user->country->code;
      $returnValue->cacheUpdate = date('Y-m-d H:i:s');

      if (!isset($rows[0])) {
        $stmt = $db->prepare('INSERT INTO osu_users (id, username, avatar_url, hit_accuracy, level, play_count, pp, rank, rank_history, best_score, playstyle, join_date, country, cache_update)
          VALUES (:id, :username, :avatar_url, :hit_accuracy, :level, :play_count, :pp, :rank, :rank_history, :best_score, :playstyle, :join_date, :country, NOW())');
        $stmt->bindValue(':id', $returnValue->id, PDO::PARAM_INT);
        $stmt->bindValue(':username', $returnValue->username, PDO::PARAM_STR);
        $stmt->bindValue(':avatar_url', $returnValue->avatarUrl, PDO::PARAM_STR);
        $stmt->bindValue(':hit_accuracy', $returnValue->hitAccuracy, PDO::PARAM_STR);
        $stmt->bindValue(':level', $returnValue->level, PDO::PARAM_INT);
        $stmt->bindValue(':play_count', $returnValue->playCount, PDO::PARAM_INT);
        $stmt->bindValue(':pp', $returnValue->pp, PDO::PARAM_STR);
        $stmt->bindValue(':rank', $returnValue->rank, PDO::PARAM_INT);
        $stmt->bindValue(':rank_history', join(',', $returnValue->rankHistory), PDO::PARAM_STR);
        $stmt->bindValue(':best_score', $returnValue->bestScore, PDO::PARAM_STR);
        $stmt->bindValue(':playstyle', $returnValue->playstyle, PDO::PARAM_STR);
        $stmt->bindValue(':join_date', $returnValue->joinDate, PDO::PARAM_STR);
        $stmt->bindValue(':country', $returnValue->country, PDO::PARAM_STR);
        $stmt->execute();
      } else {
        $stmt = $db->prepare('UPDATE osu_users
          SET username = :username, avatar_url = :avatar_url, hit_accuracy = :hit_accuracy, level = :level, play_count = :play_count, pp = :pp, rank = :rank, rank_history = :rank_history, best_score = :best_score, playstyle = :playstyle, join_date = :join_date, country = :country, cache_update = NOW()
          WHERE id = :id');
        $stmt->bindValue(':username', $returnValue->username, PDO::PARAM_STR);
        $stmt->bindValue(':avatar_url', $returnValue->avatarUrl, PDO::PARAM_STR);
        $stmt->bindValue(':hit_accuracy', $returnValue->hitAccuracy, PDO::PARAM_STR);
        $stmt->bindValue(':level', $returnValue->level, PDO::PARAM_STR);
        $stmt->bindValue(':play_count', $returnValue->playCount, PDO::PARAM_INT);
        $stmt->bindValue(':pp', $returnValue->pp, PDO::PARAM_STR);
        $stmt->bindValue(':rank', $returnValue->rank, PDO::PARAM_INT);
        $stmt->bindValue(':rank_history', join(',', $returnValue->rankHistory), PDO::PARAM_STR);
        $stmt->bindValue(':best_score', $returnValue->bestScore, PDO::PARAM_STR);
        $stmt->bindValue(':playstyle', $returnValue->playstyle, PDO::PARAM_STR);
        $stmt->bindValue(':join_date', $returnValue->joinDate, PDO::PARAM_STR);
        $stmt->bindValue(':country', $returnValue->country, PDO::PARAM_STR);
        $stmt->bindValue(':id', $returnValue->id, PDO::PARAM_INT);
        $stmt->execute();
      }
    } else {
      $returnValue = $rows[0];
    }
    
    return $returnValue;
  }

  public function getMatch($matchId) {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare('SELECT id
      FROM osu_match_events
      WHERE match_id = :match_id');
    $stmt->bindValue(':match_id', $matchId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_URL => 'https://osu.ppy.sh/community/matches/' . $matchId . '/history?full=true&since=0'
      )
    );
    $json = curl_exec($curl);
    curl_close($curl);

    $history = json_decode($json);
    foreach ($history->events as $item) {
      $found = false;
      foreach ($rows as $row) {
        if ($row->id == $item->id) {
          $found = true;
          break;
        }
      }
      if ($found === false) {
        $stmt = $db->prepare('INSERT INTO osu_match_events (id, match_id, type, timestamp, user_id, text)
          VALUES (:id, :match_id, :type, :timestamp, :user_id, :text)');
        $stmt->bindValue(':id', $item->id, PDO::PARAM_INT);
        $stmt->bindValue(':match_id', $matchId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $item->detail->type, PDO::PARAM_STR);
        $stmt->bindValue(':timestamp', str_replace('+00:00', '', $item->timestamp), PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $item->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':text', ($item->detail->type == 'other') ? $item->detail->text : null, PDO::PARAM_STR);
        $stmt->execute();

        if ($item->detail->type == 'other') {
          $stmt = $db->prepare('INSERT INTO osu_match_games (match_event, beatmap, start_time, end_time, mods, counts)
            VALUES (:match_event, :beatmap, :start_time, :end_time, :mods, 1)');
          $stmt->bindValue(':match_event', $item->id, PDO::PARAM_INT);
          $stmt->bindValue(':beatmap', $item->game->beatmap->id, PDO::PARAM_INT);
          $stmt->bindValue(':start_time', str_replace('+00:00', '', $item->game->start_time), PDO::PARAM_STR);
          $stmt->bindValue(':end_time', str_replace('+00:00', '', $item->game->end_time), PDO::PARAM_STR);
          $stmt->bindValue(':mods', join(',', $item->game->mods), PDO::PARAM_STR);
          $stmt->execute();

          foreach ($item->game->scores as $score) {
            $stmt = $db->prepare('INSERT INTO osu_match_scores (match_event, user_id, score, pass, max_combo, accuracy, mods, count_300, count_100, count_50, count_miss)
              VALUES (:match_event, :user_id, :score, :pass, :max_combo, :accuracy, :mods, :count_300, :count_100, :count_50, :count_miss)');
            $stmt->bindValue(':match_event', $item->id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $score->user_id, PDO::PARAM_INT);
            $stmt->bindValue(':score', $score->score, PDO::PARAM_INT);
            $stmt->bindValue(':pass', $score->multiplayer->pass, PDO::PARAM_INT);
            $stmt->bindValue(':max_combo', $score->max_combo, PDO::PARAM_INT);
            $stmt->bindValue(':accuracy', $score->accuracy * 100, PDO::PARAM_STR);
            $stmt->bindValue(':mods', join(',', $score->mods), PDO::PARAM_STR);
            $stmt->bindValue(':count_300', $score->statistics->count_300, PDO::PARAM_INT);
            $stmt->bindValue(':count_100', $score->statistics->count_100, PDO::PARAM_INT);
            $stmt->bindValue(':count_50', $score->statistics->count_50, PDO::PARAM_INT);
            $stmt->bindValue(':count_miss', $score->statistics->count_miss, PDO::PARAM_INT);
            $stmt->execute();
          }
        }
      }
    }

    $stmt = $db->prepare('SELECT id, type, timestamp, user_id as userId, text
      FROM osu_match_events
      WHERE match_id = :match_id
      ORDER BY timestamp ASC');
    $stmt->bindValue(':match_id', $matchId, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_OBJ);

    foreach ($events as &$event) {
      if ($event->type == 'player-joined' || $event->type == 'player-left') {
        $event->player = $this->getUser($event->userId);
      } elseif ($event->type == 'other') {
        $stmt = $db->prepare('SELECT id, beatmap, start_time, end_time, mods, counts
          FROM osu_match_games
          WHERE match_event = :match_event');
        $stmt->bindValue(':match_event', $event->id, PDO::PARAM_INT);
        $stmt->execute();
        $event->game = $stmt->fetchAll(PDO::FETCH_OBJ)[0];
        $event->game->beatmap = $this->getBeatmap($event->game->beatmap);
        $stmt = $db->prepare('SELECT id, user_id as userId, score, pass, max_combo as maxCombo, accuracy, mods, count_300 as count300, count_100 as count100, count_50 as count50, count_miss as countMiss
          FROM osu_match_scores
          WHERE match_event = :match_event');
        $stmt->bindValue(':match_event', $event->id, PDO::PARAM_INT);
        $stmt->execute();
        $event->game->scores = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($event->game->scores as &$score) {
          $score->player = $this->getUser($score->userId);
        }
      }
    }

    return $events;
  }
}

?>