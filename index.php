<!DOCTYPE html>
<html>
  <head>
    <style>
    
    </style>
  </head>
  <body>
    <div id="output"></div>
    <script>
    
      var playerData = {
        name: '', id: 0,
        position: {x: 0, y: 0, z: 0},
        orientation: {roll: 0, pitch: 0, yaw: 0},
      }
    
      const URLbase = 'https://boss.mindhackers.org/flock'
      let sendData = { playerData }
      fetch(`${URLbase}/` + 'launch.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(sendData),
      }).then(res => res.json()).then(data => {
        playerData = data
        output.innerHTML = JSON.stringify(playerData)
      })
    </script>
  </body>
</html>