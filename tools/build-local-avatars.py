from pathlib import Path
import zipfile,json,hashlib,gzip,argparse
p=argparse.ArgumentParser();p.add_argument('--archives-dir',type=Path,required=True);p.add_argument('--check',action='store_true');args=p.parse_args()
root=Path(__file__).resolve().parents[1] if Path(__file__).parent.name=='tools' else Path('/Users/sabietzki/Sites/packages/system')
pins={'core':('06e118c61373a62e7727ad45d44574c6a33a81cd','9435a292a93d3a562f40c75958028a3a7d8351553ebbaa83b4434c58516bc1c2'),'styles':('22a9abebc54ed68275da811573204ad83db646b8','c40f2e82da0cab08b6fd95df404fe3fa7ea8add8c5ae9bb621497d4f57c5a107')}
archives={};out={};manifest={'version':'10.6.0','adaptation':1,'packages':{},'styles':{},'files':{}}
for name,(ref,digest) in pins.items():
 raw=(args.archives_dir/(name+'.zip')).read_bytes()
 if hashlib.sha256(raw).hexdigest()!=digest:raise SystemExit('Archive digest differs: '+name)
 archives[name]=zipfile.ZipFile(args.archives_dir/(name+'.zip'))
 manifest['packages'][name]={'reference':ref,'sha256':digest,'source':'https://api.github.com/repos/dicebear/'+('dicebear-php' if name=='core' else 'styles')+'/zipball/'+ref}
for name in ['notionists','bottts']:
 archive=archives['styles'];raw=archive.read(next(n for n in archive.namelist() if n.endswith('/src/'+name+'.json')));definition=json.loads(raw)
 data=json.dumps(definition,ensure_ascii=False,separators=(',',':')).encode();compressed=gzip.compress(data,compresslevel=9,mtime=0)
 rel='resources/avatars/dicebear/'+name+'.json.gz';out[rel]=compressed
 manifest['styles'][name]={'file':name+'.json.gz','sha256':hashlib.sha256(compressed).hexdigest(),'rawSha256':hashlib.sha256(raw).hexdigest(),'decodedSha256':hashlib.sha256(data).hexdigest(),'decodedBytes':len(data),'meta':definition['meta']}
for rel,data in sorted(out.items()):manifest['files'][rel]=hashlib.sha256(data).hexdigest()
out['resources/avatars/dicebear/manifest.json']=(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n').encode()
for rel,data in out.items():
 dest=root/rel
 if args.check:
  if not dest.is_file() or dest.read_bytes()!=data:raise SystemExit('Generated asset differs: '+rel)
 else:dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data)
print(('Verified' if args.check else 'Generated'),len(out),'files;',sum(len(v) for v in out.values()),'bytes including manifest; renderer managed by Composer.')
