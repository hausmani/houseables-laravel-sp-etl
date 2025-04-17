from pathlib import Path

currentFileParentPath = Path(__file__).resolve().parent.parent
env_path = str(currentFileParentPath) + '/.env'
env_path_console = str(currentFileParentPath) + '/.env_from_console'


def get_env_data_as_dict(path: str):
    results = {}
    with open(path, 'r') as f:
        for line in f.readlines():
            if line.startswith('#') or not line.strip():
                continue
            split = line.rstrip().replace('\n', '').split('=', 1)
            if len(split) > 1:
                results[split[0]] = split[1]
    return results


dictFromAWSStore = get_env_data_as_dict(env_path)
dictFromAWSConsole = get_env_data_as_dict(env_path_console)

for key in dictFromAWSStore.keys():
    if key in dictFromAWSConsole:
        dictFromAWSStore[key] = dictFromAWSConsole[key]


finalStr = ''
for key in dictFromAWSStore.keys():
    finalStr = finalStr + "{}={}\n".format(key, dictFromAWSStore[key])

with open(env_path, 'w') as f:
    f.write(finalStr)
    f.close()
