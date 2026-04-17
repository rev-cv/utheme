for domain in \
  apuestachampions.com apuestaganadorligaespano.com apuestanba.com \
  apuestancaafootbalmoneyl.com apuestascampeonchampions.com apuestascampeonnba.com \
  apuestasdenba.com apuestasdeporacb.com apuestaseriea.com apuestasganadormundialf1.com \
  apuestasjleagueganador.com apuestaspeleaufc.com apuestassuperbowlganador.com \
  apuestaufc.com campeonligajaponesapuest.com campeonpremierleague.com \
  championsleagueapuestas.com comoapostarenlajleague.com comoapostarenlanfl.com \
  formula1apuestas-es.com lolesportsapuestas.com nflapuestas.com; do
  cd /home/deploy/sites/$domain 2>/dev/null && docker compose down && cd ..
done

# Удаляем папки
rm -rf /home/deploy/sites/apuestachampions.com \
  /home/deploy/sites/apuestaganadorligaespano.com \
  /home/deploy/sites/apuestanba.com \
  /home/deploy/sites/apuestancaafootbalmoneyl.com \
  /home/deploy/sites/apuestascampeonchampions.com \
  /home/deploy/sites/apuestascampeonnba.com \
  /home/deploy/sites/apuestasdenba.com \
  /home/deploy/sites/apuestasdeporacb.com \
  /home/deploy/sites/apuestaseriea.com \
  /home/deploy/sites/apuestasganadormundialf1.com \
  /home/deploy/sites/apuestasjleagueganador.com \
  /home/deploy/sites/apuestaspeleaufc.com \
  /home/deploy/sites/apuestassuperbowlganador.com \
  /home/deploy/sites/apuestaufc.com \
  /home/deploy/sites/campeonligajaponesapuest.com \
  /home/deploy/sites/campeonpremierleague.com \
  /home/deploy/sites/championsleagueapuestas.com \
  /home/deploy/sites/comoapostarenlajleague.com \
  /home/deploy/sites/comoapostarenlanfl.com \
  /home/deploy/sites/formula1apuestas-es.com \
  /home/deploy/sites/lolesportsapuestas.com \
  /home/deploy/sites/nflapuestas.com
