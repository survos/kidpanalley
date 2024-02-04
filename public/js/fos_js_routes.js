export default  ({"base_url":"","routes":{"api_genid":{"tokens":[["variable","\/","[^\/]++","id",true],["text","\/api\/.well-known\/genid"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":[],"schemes":[]},"api_entrypoint":{"tokens":[["variable",".","[^\/]++","_format",true],["variable","\/","index","index",true],["text","\/api"]],"defaults":{"_format":"","index":"index"},"requirements":{"index":"index"},"hosttokens":[],"methods":[],"schemes":[]},"api_doc":{"tokens":[["variable",".","[^\/]++","_format",true],["text","\/api\/docs"]],"defaults":{"_format":""},"requirements":[],"hosttokens":[],"methods":[],"schemes":[]},"api_jsonld_context":{"tokens":[["variable",".","jsonld","_format",true],["variable","\/","[^.]+","shortName",true],["text","\/api\/contexts"]],"defaults":{"_format":"jsonld"},"requirements":{"shortName":"[^.]+","_format":"jsonld"},"hosttokens":[],"methods":[],"schemes":[]},"_api_errors_problem":{"tokens":[["variable","\/","[^\/]++","status",true],["text","\/api\/errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_errors_hydra":{"tokens":[["variable","\/","[^\/]++","status",true],["text","\/api\/errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_errors_jsonapi":{"tokens":[["variable","\/","[^\/]++","status",true],["text","\/api\/errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_validation_errors_problem":{"tokens":[["variable","\/","[^\/]++","id",true],["text","\/api\/validation_errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_validation_errors_hydra":{"tokens":[["variable","\/","[^\/]++","id",true],["text","\/api\/validation_errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_validation_errors_jsonapi":{"tokens":[["variable","\/","[^\/]++","id",true],["text","\/api\/validation_errors"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"meili_songs":{"tokens":[["text","\/api\/meili\/Song"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_\/songs\/{id}{._format}_get":{"tokens":[["variable",".","[^\/]++","_format",true],["variable","\/","[^\/\\.]++","id",true],["text","\/api\/songs"]],"defaults":{"_format":null},"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"doctrine_songs":{"tokens":[["variable",".","[^\/]++","_format",true],["text","\/api\/songs"]],"defaults":{"_format":null},"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_\/videos\/{id}{._format}_get":{"tokens":[["variable",".","[^\/]++","_format",true],["variable","\/","[^\/\\.]++","id",true],["text","\/api\/videos"]],"defaults":{"_format":null},"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_\/videos{._format}_get_collection":{"tokens":[["variable",".","[^\/]++","_format",true],["text","\/api\/videos"]],"defaults":{"_format":null},"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"_api_meili\/{indexName}_get_collection":{"tokens":[["variable","\/","[^\/]++","indexName",true],["text","\/api\/meili"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"song_show":{"tokens":[["text","\/"],["variable","\/","[^\/]++","songId",true],["text","\/song"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]},"video_show":{"tokens":[["variable","\/","[^\/]++","videoId",true],["text","\/video"]],"defaults":[],"requirements":[],"hosttokens":[],"methods":["GET"],"schemes":[]}},"prefix":"","host":"localhost","port":"","scheme":"https","locale":""});