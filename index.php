<!DOCTYPE html>
<html>
  <head>
    <style>
    
    </style>
  </head>
  <body>
    <div id="output"></div>
    <script>
    
      const URLbase = 'https://boss.mindhackers.org/flock'
      
      const syncPlayers = data => {
        console.log(data)
      }
      
      const launchLocal = data => {
        playerData = data
        setInterval(() => {
          coms('sync.php', 'syncPlayers')
        }, 1e3)
      }
    
      var playerData = {
        name: '', id: 0,
        position: {x: 0, y: 0, z: 0},
        orientation: {roll: 0, pitch: 0, yaw: 0},
      }
      const coms = (target, callback='') => {
        let sendData = { playerData }
        fetch(`${URLbase}/` + target, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(sendData),
        }).then(res => res.json()).then(data => {
          //output.innerHTML = JSON.stringify(playerData)
          if(callback) eval(callback + '(data)')
        })
      }
      
      coms('launch.php', 'launchLocal')
    </script>
  </body>
</html>