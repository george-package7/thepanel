
<!DOCTYPE html>
<html><head>
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
  <script src="//cdn.jsdelivr.net/sockjs/1.0.3/sockjs.min.js"></script>
  <script src="public/js/stomp.js"></script>
  <style>
      .box {
          width: 440px;
          float: left;
          margin: 0 20px 0 20px;
      }

      .box div, .box input {
          border: 1px solid;
          -moz-border-radius: 4px;
          border-radius: 4px;
          width: 100%;
          padding: 5px;
          margin: 3px 0 10px 0;
      }

      .box div {
          border-color: grey;
          height: 300px;
          overflow: auto;
      }

      div code {
          display: block;
      }

      #first div code {
          -moz-border-radius: 2px;
          border-radius: 2px;
          border: 1px solid #eee;
          margin-bottom: 5px;
      }

      #second div {
          font-size: 0.8em;
      }
  </style>
  <title>RabbitMQ Web STOMP Examples : Echo Server</title>
  <link href="main.css" rel="stylesheet" type="text/css"/>
</head><body lang="en">
    <h1><a href="index.html">RabbitMQ Web STOMP Examples</a> > Echo Server</h1>

    <div id="first" class="box">
      <h2>Received</h2>
      <div></div>
      <form><input autocomplete="off" value="Type here..."></input></form>
    </div>

    <div id="second" class="box">
      <h2>Logs</h2>
      <div></div>
    </div>

    <script>
        var has_had_focus = false;
        var pipe = function(el_name, send) {
            var div  = $(el_name + ' div');
            var inp  = $(el_name + ' input');
            var form = $(el_name + ' form');

            var print = function(m, p) {
                p = (p === undefined) ? '' : JSON.stringify(p);
                div.append($("<code>").text(m + ' ' + p));
                div.scrollTop(div.scrollTop() + 10000);
            };

            if (send) {
                form.submit(function() {
                    send(inp.val());
                    inp.val('');
                    return false;
                });
            }
            return print;
        };

      // Stomp.js boilerplate
      if (location.search == '?ws') {
          var ws = new WebSocket('ws://' + window.location.hostname + ':15674/ws');
      } else {
          var ws = new SockJS('http://' + window.location.hostname + ':15674/stomp');
      }
      var client = Stomp.over(ws);

      client.debug = pipe('#second');

      var print_first = pipe('#first', function(data) {
          client.send('/queue/hello_queue2', {"content-type":"text/plain"}, data);
      });
      var on_connect = function(x) {
          id = client.subscribe("/queue/hello_queue2", function(d) {
               console.log(d.headers.type);
			   console.log(d);
			   alert(d.body);
          });
      };
      var on_error =  function() {
        console.log('error');
      };
      client.connect('test', 'test', on_connect, on_error, '/');

      $('#first input').focus(function() {
          if (!has_had_focus) {
              has_had_focus = true;
              $(this).val("");
          }
      });
    </script>
</body></html>
