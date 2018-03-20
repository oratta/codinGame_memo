import sys
import math

FILL_NODE = "0"
EMPTY_NODE = "."

def dump(value):
    print(value, file=sys.stderr)

# Don't let the machines win. You are humanity's last hope...

width = int(input())  # the number of cells on the X axis
height = int(input())  # the number of cells on the Y axis
print("width:"+str(width), file=sys.stderr)
print("height:"+str(height), file=sys.stderr)
nodes_list = [];
for i in range(height):
    line = input()  # width characters, each either 0 or .
    print(str(i) + ":" + line, file=sys.stderr)

    nodes = list(line);
    nodes_list.append(nodes)
# Write an action using print
# To debug: print("Debug messages...", file=sys.stderr)
print(nodes_list, file=sys.stderr)

# processing
echo_data_list = []
for y, nodes in zip(range(len(nodes_list)), nodes_list):
    print(y, file=sys.stderr)
    print(nodes, file=sys.stderr)
    for x,node in zip(range(len(nodes)),nodes):
        echo_data = [x, y]
        if node == FILL_NODE :
            #
            # find right neghborhood
            #
            right_neighborhood = None
            for n_x,n in zip(range(len(nodes)-x-1),nodes[x+1:]) :
                if n == FILL_NODE : # find right neighborhood
                    right_neighborhood = [n_x, y]
                    break
            if right_neighborhood is None:
                right_neighborhood = [-1, -1]
            echo_data.extend(right_neighborhood)

            #
            # find bottom neghborhood
            #
            bottom_neighborhood = None
            dump(nodes_list)
            for nl_y,nl in zip(range(len(nodes_list)-y-1),nodes_list[y+1]) :
                dump(nl)
                if nl[x] == FILL_NODE : # find bottom neighborhood
                    bottom_neighborhood = [x, nl_y]
                    break
            if bottom_neighborhood is None:
                bottom_neighborhood = [-1, -1]
            echo_data.extend(bottom_neighborhood)
        echo_data_list.append(echo_data)
# output
for echo_data in echo_data_list :
    echo_data = [str(i) for i in echo_data]
    echo_str = " ".join(echo_data)
    print (echo_str)
