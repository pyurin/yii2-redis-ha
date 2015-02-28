Client for redis Highly Available set for Yii2
=============================================
This Yii2 component is based on core yii2-redis component https://github.com/yiisoft/yii2-redis but gives ability to work with master-slave redis clusters with sentiels. <br />
It's rather simple - gets master server address from given sentinels and connects to it, then operates just like \yii\redis\Connection. <br />
It works with sentinels only, does not connect to redis hosts without sentinels.<br />

Workflow of connection process 
--------------------------------------------
Loop over given sentinels and search for an alive one that will give a master host address.<br />
If a sentinel does not respond or it's respond is empty, then we'll try to check the next one.<br />
If no successfull reply was received, then connection will fail.<br />
After we've found a redis master, we'll connect to it.<br /> 
If connection to the master server fails, we won't try anything else, we'll fail.<br />
If we sucessfully connect to the master server, we'll then check with ``role`` command if it really is master (http://redis.io/topics/sentinel-clients). <br />
If it's not - we'll fail, if it really is - we're sucessfully connected.<br />


Adviced configuration
---------------------------------------------
Redis HA cluster must be done with minimum 2 redis servers and 3 sentinels.<br />
I suppose that in termes of performance, considering this implementation it's better to have a sentinel server for each app locally, to avoid unnecessary overheads.<br />
That would not be important if we cached previously givem master server, but we dont. We ask sentinel each time before connecting to redis.<br />
That's how I see a simple architecture:

```
    -------                    -------
   |       |                  |       |
   | Redis |                  | Redis |
   |       |                  |       |
    ------- \                 /-------
       |     \               /   |
       |      \             /    |
       |       \-----------/     |
       |       |\         /|     |
       |       |  Sentinel |     |
       |       |   .    .  |     |
       |        --.------.-      |
       |         .        .      |
       |        .          .     |
  -----|-------.---        -.----|----------     
 |     |      .    |      |  .   |          |    
 |    Sentinel. . .| . . .|. . Sentinel     |    
 |         |       |      |         |       |    
 |  (unix socket)  |      |  (unix socket)  |    
 |         |       |      |         |       |    
 |         |       |      |         |       |    
 |        APP      |      |        APP      |    
 |                 |      |                 |    
  -----------------        -----------------     
   
```

Usage
---------------------------------------------
That should be defined like yii2-redis with differences:<br />
  -hostname/port/unixSocket properties must not be set.<br />
  -sentinels property must be set to an array of sentinel servers like that:,<br />
  ```
		[
				'hostname' => '10.0.0.14',
				'port' => '26380',
				'connectionTimeout' => 0.2
		],
		[
				'hostname' => '10.0.0.15',
				'port' => '26380',
				'connectionTimeout' => 0.2
		]
```
Sentinel servers are queried in the same order as exist in array.<br />


Installation
---------------------------------------------
That's part of config for composer:

```
  "require" : {
    "pyurin/yii2-redis-ha":"*@dev"
  },
  "repositories":[
  	{
  	 "type":"git",
  	 "url":"https://github.com/pyurin/yii2-redis-ha.git"
  	}
  ]
```
